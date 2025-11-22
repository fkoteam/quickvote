<?php
// api.php - API REST para control de preguntas y respuestas
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'db.php';

$db = new Database();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Respuesta por defecto
$response = ['success' => false, 'message' => 'Acción no válida'];
$respuesta_simple = "";

switch ($action) {
    case 'go':
        // Activar pregunta
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        
        if ($code && $questionId) {
            // Desactivar todas las preguntas anteriores
            $allQuestions = $db->getQuestions();
            foreach ($allQuestions as $q) {
                $db->setQuestionStatus($code, $q['id'], 'off');
            }
            
            // Activar la pregunta solicitada
            $db->setQuestionStatus($code, $questionId, 'on');
            
            $question = $db->getQuestion($questionId);
            
            $response = [
                'success' => true,
                'message' => 'Pregunta activada',
                'code' => $code,
                'question_id' => $questionId,
                'question_text' => $question['text'] ?? '',
                'yes_text' => $question['yes_text'] ?? 'SÍ',
                'no_text' => $question['no_text'] ?? 'NO'
            ];
        }
        break;
    
    case 'off':
        // Desactivar pregunta
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
        // Obtener resultados de una pregunta
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        
        if ($code && $questionId) {
            $results = $db->getResults($code, $questionId);
            $question = $db->getQuestion($questionId);
            $total = $results['yes'] + $results['no'];
            
            $winner = 'empate';
            $winnerText = 'Empate';
            if ($results['yes'] > $results['no']) { 
                $winner = 'yes';
                $winnerText = $question['yes_text'] ?? 'SÍ';
            } elseif ($results['no'] > $results['yes']) {
                $winner = 'no';
                $winnerText = $question['no_text'] ?? 'NO';
            }
            
            $response = [
                'success' => true,
                'code' => $code,
                'question_id' => $questionId,
                'question_text' => $question['text'] ?? '',
                'labels' => [
                    'yes' => $question['yes_text'] ?? 'SÍ',
                    'no' => $question['no_text'] ?? 'NO'
                ],
                'results' => $results,
                'total' => $total,
                'winner' => $winner,
                'winner_text' => $winnerText,
                'percentages' => [
                    'yes' => $total > 0 ? round(($results['yes'] / $total) * 100, 2) : 0,
                    'no' => $total > 0 ? round(($results['no'] / $total) * 100, 2) : 0
                ]
            ];
           
        }
        break;

    case 'results_simple':
        header('Content-Type: text/plain; charset=utf-8');
        // Obtener resultados de una pregunta
        $code = strtoupper($_GET['code'] ?? '');
        $questionId = intval($_GET['question_id'] ?? 0);
        
        if ($code && $questionId) {
            $results = $db->getResults($code, $questionId);
            $question = $db->getQuestion($questionId);
            $total = $results['yes'] + $results['no'];
            
            $winner = 'empate';
            $winnerText = 'Empate';
            if ($results['yes'] >= $results['no']) { //NO PUEDE HABER EMPATES
                $winner = 'yes';
                $winnerText = $question['yes_text'] ?? 'SÍ';
            } elseif ($results['no'] > $results['yes']) {
                $winner = 'no';
                $winnerText = $question['no_text'] ?? 'NO';
            }
            
            $respuesta_simple = $winner;
        }
    break;
    
    case 'check':
        // Verificar pregunta activa (usado por participantes)
        $code = strtoupper($_GET['code'] ?? '');
        
        if ($code) {
            $questionId = $db->getCurrentQuestion($code);
            
            if ($questionId) {
                $question = $db->getQuestion($questionId);
                $response = [
                    'success' => true,
                    'question_id' => $questionId,
                    'question_text' => $question['text'] ?? '',
                    'yes_text' => $question['yes_text'] ?? 'SÍ',
                    'no_text' => $question['no_text'] ?? 'NO'
                ];
            } else {
                $response = [
                    'success' => true,
                    'question_id' => null,
                    'question_text' => '',
                    'yes_text' => '',
                    'no_text' => ''
                ];
            }
        }
        break;
    
    case 'answer':
        // Registrar respuesta de participante
        $code = strtoupper($_POST['code'] ?? '');
        $questionId = intval($_POST['question_id'] ?? 0);
        $participantId = $_POST['participant_id'] ?? '';
        $answer = $_POST['answer'] ?? '';
        
        if ($code && $questionId && $participantId && in_array($answer, ['yes', 'no'])) {
            // Verificar que la pregunta esté activa
            $status = $db->getQuestionStatus($code, $questionId);
            
            if ($status === 'on') {
                $db->saveAnswer($code, $questionId, $participantId, $answer);
                $response = [
                    'success' => true,
                    'message' => 'Respuesta registrada'
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'La pregunta no está activa'
                ];
            }
        }
        break;
    
    case 'reset':
        // Reiniciar respuestas de una pregunta
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
        // Reiniciar todas las respuestas de una instancia
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
        // Estado general del sistema (útil para debugging)
        $code = strtoupper($_GET['code'] ?? '');
        
        if ($code) {
            $questions = $db->getQuestions();
            $statuses = [];
            
            foreach ($questions as $q) {
                $statuses[] = [
                    'id' => $q['id'],
                    'text' => $q['text'],
                    'yes_text' => $q['yes_text'],
                    'no_text' => $q['no_text'],
                    'status' => $db->getQuestionStatus($code, $q['id']),
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
        // Listar todas las preguntas
        $questions = $db->getQuestions();
        $response = [
            'success' => true,
            'questions' => $questions
        ];
        break;
}

if (empty($respuesta_simple)) {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo $respuesta_simple;
}

?>
