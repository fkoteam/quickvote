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
        .btn { padding: 12px 25px; border: none; border-radius: 8px; font-size: 1em; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-primary { background: linear-gradient(135deg, #8a2be2 0%, #4a0080 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(138, 43, 226, 0.5); }
        .btn-danger { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); color: white; padding: 8px 15px; font-size: 0.9em; }
        .btn-warning { background: linear-gradient(135deg, #f5af19 0%, #f12711 100%); color: white; padding: 8px 15px; font-size: 0.9em; }
        .btn-secondary { background: #444; color: #fff; padding: 5px 10px; font-size: 0.8em; margin-left: 5px; }
        .question-item, .instance-item { background: rgba(0, 0, 0, 0.3); padding: 20px; border-radius: 10px; margin-bottom: 15px; border: 1px solid rgba(138, 43, 226, 0.2); transition: all 0.2s; }
        .question-item:hover, .instance-item:hover { border-color: rgba(138, 43, 226, 0.5); box-shadow: 0 0 20px rgba(138, 43, 226, 0.2); }
        .question-header, .instance-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; }
        .question-text { flex: 1; font-size: 1.1em; color: #fff; }
        .question-text strong { color: #8a2be2; }
        .question-labels { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .label-tag { padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; background: rgba(0, 176, 155, 0.2); border: 1px solid rgba(0, 176, 155, 0.5); color: #96c93d; }
        .label-timer { background: rgba(245, 175, 25, 0.2); border: 1px solid rgba(245, 175, 25, 0.5); color: #f5af19; }
        .instance-code { font-size: 1.5em; font-weight: 700; color: #8a2be2; letter-spacing: 3px; text-shadow: 0 0 20px rgba(138, 43, 226, 0.5); }
        .api-section { background: rgba(0, 0, 0, 0.3); padding: 20px; border-radius: 10px; margin-top: 20px; border: 1px solid rgba(138, 43, 226, 0.2); }
        .api-section h3 { color: #8a2be2; margin-bottom: 10px; margin-top: 15px; }
        .api-endpoint { background: #000; color: #96c93d; padding: 12px 15px; border-radius: 6px; font-family: 'Courier New', monospace; margin-bottom: 10px; overflow-x: auto; border: 1px solid rgba(150, 201, 61, 0.3); }
        .api-buttons { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .instance-select { padding: 8px 12px; border-radius: 6px; background: rgba(0, 0, 0, 0.5); border: 1px solid rgba(138, 43, 226, 0.5); color: #fff; font-size: 0.9em; width: auto; }
        .btn-go { background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%); color: white; padding: 8px 15px; font-size: 0.9em; }
        .btn-off { background: linear-gradient(135deg, #606060 0%, #404040 100%); color: white; padding: 8px 15px; font-size: 0.9em; }
        .btn-results { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 15px; font-size: 0.9em; }
        .option-input-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .screen-btn { background: #3498db; color:white; border:none; padding: 5px 10px; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.8em; margin-left: 10px;}
    </style>
    <script>
    function openApi(action, code, questionId) {
        const url = `api.php?action=${action}&code=${code}&question_id=${questionId}`;
        window.open(url, '_blank');
    }
    
    function addOption() {
        const container = document.getElementById('options-container');
        const div = document.createElement('div');
        div.className = 'option-input-row';
        div.innerHTML = `
            <input type="text" name="options[]" placeholder="Opción de respuesta" required>
            <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()" style="padding: 0 15px;">X</button>
        `;
        container.appendChild(div);
    }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✦ Panel de Administración</h1>
            <a href="index.php">← Volver a inicio</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType === 'error' ? 'error' : ''; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Crear instancia -->
        <div class="section">
            <h2>📊 Instancias de Encuesta</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_instance">
                <div class="form-group">
                    <label>Código de la instancia:</label>
                    <input type="text" name="code" required placeholder="Ej: ABC123" pattern="[A-Za-z0-9]+" maxlength="10">
                </div>
                <button type="submit" class="btn btn-primary">Crear Instancia</button>
            </form>

            <div class="instance-list">
                <h3 style="color: rgba(255,255,255,0.7); margin: 20px 0 15px;">Instancias Activas:</h3>
                <?php foreach ($instances as $instance): ?>
                    <div class="instance-item">
                        <div class="instance-header">
                            <div>
                                <span class="instance-code"><?php echo htmlspecialchars($instance['code']); ?></span>
                                <a href="screen.php?code=<?php echo $instance['code']; ?>" target="_blank" class="screen-btn">📺 Abrir Pantalla Visual</a>
                                <br>
                                <small>Creado: <?php echo $instance['created_at']; ?></small>
                            </div>
                            <div class="instance-actions">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Reiniciar TODAS las respuestas de esta instancia?');">
                                    <input type="hidden" name="action" value="reset_all_answers">
                                    <input type="hidden" name="code" value="<?php echo $instance['code']; ?>">
                                    <button type="submit" class="btn btn-warning">🔄 Reset Respuestas</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta instancia y todas sus respuestas?');">
                                    <input type="hidden" name="action" value="delete_instance">
                                    <input type="hidden" name="code" value="<?php echo $instance['code']; ?>">
                                    <button type="submit" class="btn btn-danger">🗑️ Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Gestión de preguntas (MODIFICADO PARA MULTI-OPCIÓN) -->
        <div class="section">
            <h2>❓ Gestión de Preguntas</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_question">
                <div class="form-group">
                    <label>Texto de la pregunta:</label>
                    <textarea name="question_text" required placeholder="Escribe tu pregunta aquí..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>⏱️ Tiempo límite (segundos):</label>
                    <input type="number" name="timer_seconds" value="0" min="0" placeholder="0 = Manual (sin límite automático)">
                    <small>Si pones 0, el apagado será manual.</small>
                </div>

                <div class="form-group">
                    <label>Opciones de Respuesta:</label>
                    <div id="options-container">
                        <div class="option-input-row"><input type="text" name="options[]" value="SÍ" required></div>
                        <div class="option-input-row"><input type="text" name="options[]" value="NO" required></div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addOption()">+ Añadir Opción</button>
                </div>

                <button type="submit" class="btn btn-primary">Agregar Pregunta</button>
            </form>

            <div class="question-list">
                <h3 style="color: rgba(255,255,255,0.7); margin: 20px 0 15px;">Preguntas Existentes:</h3>
                <?php foreach ($questions as $question): ?>
                    <div class="question-item">
                        <div class="question-header">
                            <div class="question-text">
                                <strong>ID <?php echo $question['id']; ?>:</strong> 
                                <?php echo htmlspecialchars($question['text']); ?>
                                
                                <div class="question-labels">
                                    <?php if($question['timer_seconds'] > 0): ?>
                                        <span class="label-tag label-timer">⏱️ <?php echo $question['timer_seconds']; ?>s</span>
                                    <?php else: ?>
                                        <span class="label-tag label-timer">Manual</span>
                                    <?php endif; ?>
                                    
                                    <?php foreach($question['options'] as $opt): ?>
                                        <span class="label-tag"><?php echo htmlspecialchars($opt['text']); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="question-actions">
                                <div class="api-buttons">
                                    <select class="instance-select" id="instance_q<?php echo $question['id']; ?>">
                                        <?php foreach ($instances as $inst): ?>
                                            <option value="<?php echo $inst['code']; ?>"><?php echo $inst['code']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-go" onclick="openApi('go', document.getElementById('instance_q<?php echo $question['id']; ?>').value, <?php echo $question['id']; ?>)">▶ GO</button>
                                    <button class="btn btn-off" onclick="openApi('off', document.getElementById('instance_q<?php echo $question['id']; ?>').value, <?php echo $question['id']; ?>)">⏹ OFF</button>
                                    <button class="btn btn-results" onclick="openApi('results', document.getElementById('instance_q<?php echo $question['id']; ?>').value, <?php echo $question['id']; ?>)">Results</button>
                                </div>
                                <div style="margin-top: 10px;">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta pregunta?');">
                                        <input type="hidden" name="action" value="delete_question">
                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                        <button type="submit" class="btn btn-danger">🗑️ Eliminar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Documentación de API (CONSERVADA) -->
        <div class="section">
            <h2>🔌 Control de Preguntas por API</h2>
            <div class="api-section">
                <h3>Activar pregunta:</h3>
                <div class="api-endpoint">GET /api.php?action=go&code=CODIGO&question_id=ID</div>
                
                <h3>Desactivar pregunta:</h3>
                <div class="api-endpoint">GET /api.php?action=off&code=CODIGO&question_id=ID</div>
                
                <h3>Ver resultados:</h3>
                <div class="api-endpoint">GET /api.php?action=results&code=CODIGO&question_id=ID</div>

                <h3>Reiniciar respuestas:</h3>
                <div class="api-endpoint">GET /api.php?action=reset&code=CODIGO&question_id=ID</div>
            </div>
        </div>
    </div>
</body>
</html>
