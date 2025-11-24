<?php
// api.php - API REST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'db.php';

$db = new Database();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Acción no válida'];

switch ($action) {
    case 'go':
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        
        if ($code && $questionId) {
            // Apagar anteriores
            $current = $db->getCurrentStatus($code);
            if ($current) {
                $db->setQuestionStatus($code, $current['question_id'], 'off');
            }
            // Activar nueva (esto registra el start_time)
            $db->setQuestionStatus($code, $questionId, 'on');
            
            $response = ['success' => true, 'message' => 'Pregunta activada'];
        }
        break;
    
    case 'off':
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        if ($code && $questionId) {
            $db->setQuestionStatus($code, $questionId, 'off');
            $response = ['success' => true, 'message' => 'Pregunta desactivada'];
        }
        break;
    
    case 'results':
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        if ($code && $questionId) {
            $data = $db->getResults($code, $questionId);
            $question = $db->getQuestion($questionId);
            
            $response = [
                'success' => true,
                'question_text' => $question['text'] ?? '',
                'results' => $data['data'],
                'total' => $data['total']
            ];
        }
        break;
    
    case 'check':
        $code = strtoupper($_GET['code'] ?? '');
        if ($code) {
            $status = $db->getCurrentStatus($code);
            
            if ($status) {
                $q = $db->getQuestion($status['question_id']);
                
                // Calcular tiempo restante
                $remaining = null;
                if ($status['timer_seconds'] > 0) {
                    $elapsed = time() - $status['start_time'];
                    $remaining = max(0, $status['timer_seconds'] - $elapsed);
                    
                    // Si el tiempo expiró en el servidor, aunque el estado sea 'on', informamos al cliente 0
                    if ($remaining <= 0) $remaining = 0;
                }

                $response = [
                    'success' => true,
                    'question_id' => $q['id'],
                    'question_text' => $q['text'],
                    'options' => $q['options'],
                    'timer_total' => $q['timer_seconds'],
                    'timer_remaining' => $remaining
                ];
            } else {
                $response = [
                    'success' => true,
                    'question_id' => null
                ];
            }
        }
        break;
    
    case 'answer':
        $code = strtoupper($_POST['code'] ?? '');
        $questionId = intval($_POST['question_id'] ?? 0);
        $participantId = $_POST['participant_id'] ?? '';
        $answerIndex = intval($_POST['answer'] ?? 0); // Recibimos el índice numérico
        
        if ($code && $questionId && $participantId && $answerIndex) {
            if ($db->saveAnswer($code, $questionId, $participantId, $answerIndex)) {
                $response = ['success' => true, 'message' => 'Respuesta registrada'];
            } else {
                $response = ['success' => false, 'message' => 'Tiempo agotado o pregunta inactiva'];
            }
        }
        break;
    
    case 'reset':
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        if ($code && $questionId) {
            $db->resetAnswers($code, $questionId);
            $response = ['success' => true, 'message' => 'Respuestas reiniciadas'];
        }
        break;
        
    case 'reset_all':
        $code = strtoupper($_GET['code'] ?? '');
        if ($code) {
            $db->resetAllAnswers($code);
            $response = ['success' => true];
        }
        break;
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
