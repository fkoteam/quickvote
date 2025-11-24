<?php
// api.php
header('Content-Type: application/json');
require_once 'db.php';
$db = new Database();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$code = strtoupper($_GET['code'] ?? $_POST['code'] ?? '');
$qId = intval($_GET['question_id'] ?? $_POST['question_id'] ?? 0);

$response = ['success' => false];

switch($action) {
    case 'go':
        if ($code && $qId) {
            // Apagar cualquier otra pregunta activa
            $current = $db->getCurrentStatus($code);
            if ($current) $db->setQuestionStatus($code, $current['question_id'], 'off');
            
            // Activar la nueva
            $db->setQuestionStatus($code, $qId, 'on');
            $response = ['success' => true, 'message' => 'Pregunta Activada'];
        }
        break;

    case 'off':
        if ($code && $qId) {
            $db->setQuestionStatus($code, $qId, 'off');
            $response = ['success' => true, 'message' => 'Pregunta Detenida'];
        }
        break;

    case 'check':
        // Polling del participante y la pantalla
        if ($code) {
            $status = $db->getCurrentStatus($code);
            if ($status) {
                $q = $db->getQuestion($status['question_id']);
                
                // Calcular tiempo restante si aplica
                $remaining = null;
                if ($q['timer_seconds'] > 0) {
                    $elapsed = time() - $status['start_time'];
                    $remaining = max(0, $q['timer_seconds'] - $elapsed);
                    
                    // Si el tiempo expiró server-side pero sigue 'on', lo notificamos
                    // (La pantalla y cliente se bloquearán visualmente, el admin debe dar OFF manual o auto-off script)
                }

                $response = [
                    'success' => true,
                    'active' => true,
                    'question' => [
                        'id' => $q['id'],
                        'text' => $q['text'],
                        'options' => $q['options'],
                        'timer_total' => $q['timer_seconds'],
                        'timer_remaining' => $remaining
                    ]
                ];
            } else {
                $response = ['success' => true, 'active' => false];
            }
        }
        break;

    case 'answer':
        $uid = $_POST['participant_id'] ?? '';
        $ans = intval($_POST['answer_index'] ?? 0);
        
        if ($code && $qId && $uid && $ans) {
            if ($db->saveAnswer($code, $qId, $uid, $ans)) {
                $response = ['success' => true, 'message' => 'Voto registrado'];
            } else {
                $response = ['success' => false, 'message' => 'Tiempo agotado o error'];
            }
        }
        break;

    case 'results':
        if ($code && $qId) {
            $data = $db->getResults($code, $qId);
            $q = $db->getQuestion($qId);
            $response = [
                'success' => true,
                'question_text' => $q['text'],
                'results' => $data['data'],
                'total' => $data['total']
            ];
        }
        break;
        
    case 'reset':
        if ($code && $qId) {
            $db->resetAnswers($code, $qId);
            $response = ['success' => true];
        }
        break;
}

echo json_encode($response);
?>
