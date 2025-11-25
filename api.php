<?php
// api.php - API REST actualizada para opciones múltiples
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'db.php';

$db = new Database();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$response = ['success' => false, 'message' => 'Acción no válida'];
$respuesta_simple = "";

switch ($action) {
    case 'go':
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        
        if ($code && $questionId) {
            // Desactivar todas las preguntas
            $allQuestions = $db->getQuestions();
            foreach ($allQuestions as $q) {
                $db->setQuestionStatus($code, $q['id'], 'off');
            }
            
            // Activar la pregunta solicitada
            $db->setQuestionStatus($code, $questionId, 'on');
            
            $question = $db->getQuestion($questionId);
            $optionLabels = json_decode($question['option_labels'], true);
            
            $response = [
                'success' => true,
                'message' => 'Pregunta activada',
                'code' => $code,
                'question_id' => $questionId,
                'question_text' => $question['text'],
                'num_options' => $question['num_options'],
                'option_labels' => $optionLabels,
                'auto_close' => $question['auto_close'] == 1,
                'close_seconds' => $question['close_seconds']
            ];
        }
        break;
    
    case 'off':
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        
        if ($code && $questionId) {
            $db->setQuestionStatus($code, $questionId, 'off');
            
            $response = [
                'success' => true,
                'message' => 'Pregunta desactivada',
                'code' => $code,
                'question_id' => $questionId
            ];
        }
        break;
    
    case 'results':
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        
        if ($code && $questionId) {
            $results = $db->getResults($code, $questionId);
            $question = $db->getQuestion($questionId);
            $optionLabels = json_decode($question['option_labels'], true);
            
            $total = array_sum($results);
            $maxVotes = 0;
            $winner = null;
            $percentages = [];
            
            for ($i = 1; $i <= $question['num_options']; $i++) {
                $votes = $results[$i] ?? 0;
                $percentages[$i] = $total > 0 ? round(($votes / $total) * 100, 2) : 0;
                
                if ($votes > $maxVotes) {
                    $maxVotes = $votes;
                    $winner = $i;
                }
            }
            
            $response = [
                'success' => true,
                'code' => $code,
                'question_id' => $questionId,
                'question_text' => $question['text'],
                'num_options' => $question['num_options'],
                'option_labels' => $optionLabels,
                'results' => $results,
                'total' => $total,
                'winner' => $winner,
                'winner_text' => $winner ? $optionLabels[$winner - 1] : null,
                'percentages' => $percentages
            ];
        }
        break;

    case 'results_simple':
        header('Content-Type: text/plain; charset=utf-8');
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        
        if ($code && $questionId) {
            $results = $db->getResults($code, $questionId);
            $question = $db->getQuestion($questionId);
            
            $maxVotes = 0;
            $winner = 1;
            
            for ($i = 1; $i <= $question['num_options']; $i++) {
                $votes = $results[$i] ?? 0;
                if ($votes > $maxVotes) {
                    $maxVotes = $votes;
                    $winner = $i;
                }
            }


                        
            
            $respuesta_simple = (string)$winner;
        }
        break;


    case 'send_emails':
        $code = strtoupper($_GET['code'] ?? '');
        if ($code) {
            $questions = $db->getQuestions();
            $emails = $db->getParticipantsWithEmail($code);
            $count = 0;
            
            // Pre-calcular resultados globales para eficiencia
            $globalStats = [];
            foreach ($questions as $q) {
                $rawResults = $db->getResults($code, $q['id']);
                $total = array_sum($rawResults);
                $percentages = [];
                for($i=1; $i<=$q['num_options']; $i++){
                    $votes = $rawResults[$i] ?? 0;
                    $percentages[$i] = $total > 0 ? round(($votes/$total)*100, 1) : 0;
                }
                $globalStats[$q['id']] = $percentages;
            }

            // Enviar a cada usuario
            foreach ($emails as $email) {
                $userAnswers = $db->getUserAnswers($code, $email);
                
                $subject = "Resultados de la Encuesta: $code";
                $message = "<html><body style='font-family: sans-serif; background: #f4f4f4; padding: 20px;'>";
                $message .= "<div style='max-width: 600px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 10px;'>";
                $message .= "<h1 style='color: #8a2be2;'>Tus Resultados ($code)</h1>";
                
                foreach ($questions as $q) {
                    // Solo incluir preguntas que el usuario respondió
                    if (isset($userAnswers[$q['id']])) {
                        $userChoiceIdx = $userAnswers[$q['id']];
                        $labels = json_decode($q['option_labels'], true);
                        $userLabel = $labels[$userChoiceIdx - 1] ?? 'Desconocido';
                        
                        $message .= "<div style='margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;'>";
                        $message .= "<h3 style='margin-bottom: 5px;'>{$q['text']}</h3>";
                        $message .= "<p>Tú elegiste: <strong>{$userLabel}</strong></p>";
                        $message .= "<div style='background: #eee; border-radius: 5px; padding: 10px; font-size: 0.9em;'>";
                        $message .= "<strong>Resultados globales:</strong><br>";
                        
                        foreach ($globalStats[$q['id']] as $idx => $pct) {
                            $optLabel = $labels[$idx - 1];
                            $isUser = ($idx == $userChoiceIdx) ? " (Tú)" : "";
                            $barColor = ($idx == $userChoiceIdx) ? "#8a2be2" : "#ccc";
                            $message .= "<div style='margin-top: 5px;'>$optLabel $isUser: $pct%</div>";
                            $message .= "<div style='height: 6px; width: {$pct}%; background: $barColor; border-radius: 3px;'></div>";
                        }
                        $message .= "</div></div>";
                    }
                }
                
                $message .= "</div></body></html>";
                
                // Headers para HTML
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= 'From: Encuestas <no-reply@tudominio.com>' . "\r\n";

                if(mail($email, $subject, $message, $headers)) {
                    $count++;
                }
            }
            
            $response = ['success' => true, 'message' => "Enviados $count correos exitosamente."];
        }
        break;
    
    case 'check':
        $code = strtoupper($_GET['code'] ?? '');
        
        if ($code) {
            $currentQuestion = $db->getCurrentQuestion($code);
            
            if ($currentQuestion) {
                $questionId = $currentQuestion['question_id'];
                $question = $db->getQuestion($questionId);
                $optionLabels = json_decode($question['option_labels'], true);
                
                // Calcular tiempo restante si es auto-close
                $timeRemaining = null;
                if ($question['auto_close'] && $currentQuestion['started_at']) {
                    $startTime = strtotime($currentQuestion['started_at']);
                    $now = time();
                    $elapsed = $now - $startTime;
                    $timeRemaining = max(0, $question['close_seconds'] - $elapsed);
                    
                    // Si el tiempo se agotó, cerrar automáticamente
                    if ($timeRemaining <= 0) {
                        $db->setQuestionStatus($code, $questionId, 'off');
                        $response = [
                            'success' => true,
                            'question_id' => null,
                            'time_expired' => true
                        ];
                        break;
                    }
                }
                
                $response = [
                    'success' => true,
                    'question_id' => $questionId,
                    'question_text' => $question['text'],
                    'num_options' => $question['num_options'],
                    'option_labels' => $optionLabels,
                    'auto_close' => $question['auto_close'] == 1,
                    'close_seconds' => $question['close_seconds'],
                    'time_remaining' => $timeRemaining,
                    'started_at' => $currentQuestion['started_at']
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
        $answer = intval($_POST['answer'] ?? 0);
        session_start();
        $email = $_SESSION['participant_email'] ?? null;
        
        if ($code && $questionId && $participantId && $answer > 0) {
            $statusData = $db->getQuestionStatus($code, $questionId);
            
            if ($statusData['status'] === 'on') {
                $question = $db->getQuestion($questionId);
                
                // Verificar que la respuesta está en el rango válido
                if ($answer <= $question['num_options']) {
                    $db->saveAnswer($code, $questionId, $participantId, $answer, $email);
                    $response = [
                        'success' => true,
                        'message' => 'Respuesta registrada'
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Respuesta inválida'
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => 'La pregunta no está activa'
                ];
            }
        }
        break;
    
    case 'reset':
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        
        if ($code && $questionId) {
            $db->resetAnswers($code, $questionId);
            $response = [
                'success' => true,
                'message' => 'Respuestas reiniciadas',
                'code' => $code,
                'question_id' => $questionId
            ];
        }
        break;
    
    case 'reset_all':
        $code = strtoupper($_GET['code'] ?? '');
        
        if ($code) {
            $db->resetAllAnswers($code);
            $response = [
                'success' => true,
                'message' => 'Todas las respuestas reiniciadas',
                'code' => $code
            ];
        }
        break;
    
    case 'status':
        $code = strtoupper($_GET['code'] ?? '');
        
        if ($code) {
            $questions = $db->getQuestions();
            $statuses = [];
            
            foreach ($questions as $q) {
                $statusData = $db->getQuestionStatus($code, $q['id']);
                $statuses[] = [
                    'id' => $q['id'],
                    'text' => $q['text'],
                    'num_options' => $q['num_options'],
                    'option_labels' => json_decode($q['option_labels'], true),
                    'auto_close' => $q['auto_close'] == 1,
                    'close_seconds' => $q['close_seconds'],
                    'status' => $statusData['status'],
                    'results' => $db->getResults($code, $q['id'])
                ];
            }
            
            $response = [
                'success' => true,
                'code' => $code,
                'questions' => $statuses
            ];
        }
        break;
    
    case 'questions':
        $questions = $db->getQuestions();
        $formattedQuestions = [];
        
        foreach ($questions as $q) {
            $formattedQuestions[] = [
                'id' => $q['id'],
                'text' => $q['text'],
                'num_options' => $q['num_options'],
                'option_labels' => json_decode($q['option_labels'], true),
                'auto_close' => $q['auto_close'] == 1,
                'close_seconds' => $q['close_seconds']
            ];
        }
        
        $response = [
            'success' => true,
            'questions' => $formattedQuestions
        ];
        break;
}

if (empty($respuesta_simple)) {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo $respuesta_simple;
}
?>
