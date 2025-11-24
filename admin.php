<?php
// admin.php - Panel de administración
session_start();
require_once 'db.php';

$db = new Database();
$message = '';
$messageType = 'success';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_question') {
        $text = trim($_POST['question_text'] ?? '');
        $timer = intval($_POST['timer_seconds'] ?? 0);
        $options = $_POST['options'] ?? [];
        
        // Filtrar opciones vacías
        $options = array_filter($options, function($val) { return trim($val) !== ''; });

        if ($text && count($options) >= 2) {
            $db->createQuestion($text, $options, $timer);
            $message = 'Pregunta creada exitosamente';
        } else {
            $message = 'Error: Debes escribir la pregunta y al menos 2 opciones.';
            $messageType = 'error';
        }
    } elseif ($action === 'delete_question') {
        $id = $_POST['question_id'] ?? 0;
        if ($id) {
            $db->deleteQuestion($id);
            $message = 'Pregunta eliminada';
        }
    } elseif ($action === 'create_instance') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($code) {
            try {
                $db->createSurveyInstance($code);
                $message = 'Instancia creada: ' . $code;
            } catch (Exception $e) {
                $message = 'Error: El código ya existe';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'delete_instance') {
        $code = $_POST['code'] ?? '';
        if ($code) {
            $db->deleteSurveyInstance($code);
            $message = 'Instancia eliminada: ' . $code;
        }
    } elseif ($action === 'reset_answers') {
        $code = $_POST['code'] ?? '';
        $questionId = $_POST['question_id'] ?? 0;
        if ($code && $questionId) {
            $db->resetAnswers($code, $questionId);
            $message = 'Respuestas reiniciadas para la pregunta ' . $questionId;
        }
    } elseif ($action === 'reset_all_answers') {
        $code = $_POST['code'] ?? '';
        if ($code) {
            $db->resetAllAnswers($code);
            $message = 'Todas las respuestas reiniciadas para ' . $code;
        }
    }
}

$questions = $db->getQuestions();
$instances = $db->getSurveyInstances();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <style>
        /* ESTILOS ORIGINALES CONSERVADOS */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0a0a0f; padding: 20px; min-height: 100vh; color: #fff; }
        .header { background: linear-gradient(135deg, rgba(138, 43, 226, 0.3) 0%, rgba(74, 0, 128, 0.3) 100%); border: 1px solid rgba(138, 43, 226, 0.5); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; text-shadow: 0 0 30px rgba(138, 43, 226, 0.5); }
        .header a { color: rgba(255, 255, 255, 0.7); text-decoration: none; transition: color 0.3s; }
        .header a:hover { color: #fff; }
        .container { max-width: 1200px; margin: 0 auto; }
        .message { background: linear-gradient(135deg, rgba(0, 176, 155, 0.2) 0%, rgba(150, 201, 61, 0.2) 100%); border: 1px solid rgba(0, 176, 155, 0.5); color: #96c93d; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.error { background: linear-gradient(135deg, rgba(235, 51, 73, 0.2) 0%, rgba(244, 92, 67, 0.2) 100%); border: 1px solid rgba(235, 51, 73, 0.5); color: #f45c43; }
        .section { background: rgba(20, 20, 30, 0.9); padding: 30px; border-radius: 15px; margin-bottom: 30px; border: 1px solid rgba(138, 43, 226, 0.2); }
        .section h2 { color: #fff; margin-bottom: 25px; font-size: 1.8em; border-bottom: 2px solid rgba(138, 43, 226, 0.5); padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: rgba(255, 255, 255, 0.8); }
        input[type="text"], input[type="number"], textarea, select { width: 100%; padding: 12px 15px; border: 2px solid rgba(138, 43, 226, 0.3); border-radius: 8px; font-size: 1em; transition: all 0.3s; background: rgba(0, 0, 0, 0.5); color: #fff; }
        input:focus, textarea:focus, select:focus { outline: none; border-color: #8a2be2; box-shadow: 0 0 15px rgba(138, 43, 226, 0.3); }
        textarea { min-height: 80px; resize: vertical; font-family: inherit; }
