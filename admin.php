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
        $yesText = trim($_POST['yes_text'] ?? 'SÍ');
        $noText = trim($_POST['no_text'] ?? 'NO');
        if ($text) {
            $db->createQuestion($text, $yesText ?: 'SÍ', $noText ?: 'NO');
            $message = 'Pregunta creada exitosamente';
        }
    } elseif ($action === 'update_question') {
        $id = $_POST['question_id'] ?? 0;
        $text = trim($_POST['question_text'] ?? '');
        $yesText = trim($_POST['yes_text'] ?? 'SÍ');
        $noText = trim($_POST['no_text'] ?? 'NO');
        if ($id && $text) {
            $db->updateQuestion($id, $text, $yesText ?: 'SÍ', $noText ?: 'NO');
            $message = 'Pregunta actualizada';
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0f;
            padding: 20px;
            min-height: 100vh;
            color: #fff;
        }

        .header {
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.3) 0%, rgba(74, 0, 128, 0.3) 100%);
            border: 1px solid rgba(138, 43, 226, 0.5);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 0 30px rgba(138, 43, 226, 0.5);
        }

        .header a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s;
        }

        .header a:hover {
            color: #fff;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .message {
            background: linear-gradient(135deg, rgba(0, 176, 155, 0.2) 0%, rgba(150, 201, 61, 0.2) 100%);
            border: 1px solid rgba(0, 176, 155, 0.5);
            color: #96c93d;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.error {
            background: linear-gradient(135deg, rgba(235, 51, 73, 0.2) 0%, rgba(244, 92, 67, 0.2) 100%);
            border: 1px solid rgba(235, 51, 73, 0.5);
            color: #f45c43;
        }

        .section {
            background: rgba(20, 20, 30, 0.9);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(138, 43, 226, 0.2);
        }

        .section h2 {
            color: #fff;
            margin-bottom: 25px;
            font-size: 1.8em;
            border-bottom: 2px solid rgba(138, 43, 226, 0.5);
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
        }

        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(138, 43, 226, 0.3);
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
            background: rgba(0, 0, 0, 0.5);
            color: #fff;
        }

        input[type="text"]::placeholder,
        textarea::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #8a2be2;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.3);
        }

        textarea {
            min-height: 80px;
            resize: vertical;
            font-family: inherit;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8a2be2 0%, #4a0080 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(138, 43, 226, 0.5);
        }

        .btn-danger {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
        }

        .btn-danger:hover {
            box-shadow: 0 5px 15px rgba(235, 51, 73, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f5af19 0%, #f12711 100%);
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
        }

        .btn-warning:hover {
            box-shadow: 0 5px 15px rgba(245, 175, 25, 0.4);
        }

        .btn-edit {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
            margin-right: 10px;
        }

        .btn-edit:hover {
            box-shadow: 0 5px 15px rgba(56, 239, 125, 0.4);
        }

        .question-list,
        .instance-list {
            margin-top: 25px;
        }

        .question-item,
        .instance-item {
            background: rgba(0, 0, 0, 0.3);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid rgba(138, 43, 226, 0.2);
            transition: all 0.2s;
        }

        .question-item:hover,
        .instance-item:hover {
            border-color: rgba(138, 43, 226, 0.5);
            box-shadow: 0 0 20px rgba(138, 43, 226, 0.2);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 15px;
        }

        .question-text {
            flex: 1;
            font-size: 1.1em;
            color: #fff;
        }

        .question-text strong {
            color: #8a2be2;
        }

        .question-labels {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .label-tag {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .label-yes {
            background: rgba(0, 176, 155, 0.2);
            border: 1px solid rgba(0, 176, 155, 0.5);
            color: #96c93d;
        }

        .label-no {
            background: rgba(235, 51, 73, 0.2);
            border: 1px solid rgba(235, 51, 73, 0.5);
            color: #f45c43;
        }

        .question-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .instance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .instance-code {
            font-size: 1.5em;
            font-weight: 700;
            color: #8a2be2;
            letter-spacing: 3px;
            text-shadow: 0 0 20px rgba(138, 43, 226, 0.5);
        }

        .instance-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .api-section {
            background: rgba(0, 0, 0, 0.3);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border: 1px solid rgba(138, 43, 226, 0.2);
        }

        .api-section h3 {
            color: #8a2be2;
            margin-bottom: 10px;
            margin-top: 15px;
        }

        .api-section h3:first-child {
            margin-top: 0;
        }

        .api-endpoint {
            background: #000;
            color: #96c93d;
            padding: 12px 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            margin-bottom: 10px;
            overflow-x: auto;
            border: 1px solid rgba(150, 201, 61, 0.3);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(20, 20, 30, 0.98);
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(138, 43, 226, 0.5);
            box-shadow: 0 0 60px rgba(138, 43, 226, 0.3);
        }

        .modal-header {
            margin-bottom: 20px;
            font-size: 1.5em;
            color: #fff;
        }

        .modal-footer {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        small {
            color: rgba(255, 255, 255, 0.5);
        }

        @media (max-width: 768px) {
            .question-header,
            .instance-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .question-actions,
            .instance-actions {
                width: 100%;
            }

            .form-row {
                flex-direction: column;
            }
        }
    </style>
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

        <!-- Crear instancia de encuesta -->
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

        <!-- CRUD de preguntas -->
        <div class="section">
            <h2>❓ Gestión de Preguntas</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_question">
                <div class="form-group">
                    <label>Texto de la pregunta:</label>
                    <textarea name="question_text" required placeholder="Escribe tu pregunta aquí..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Texto botón afirmativo:</label>
                        <input type="text" name="yes_text" placeholder="SÍ" value="SÍ">
                    </div>
                    <div class="form-group">
                        <label>Texto botón negativo:</label>
                        <input type="text" name="no_text" placeholder="NO" value="NO">
                    </div>
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
                                    <span class="label-tag label-yes">✓ <?php echo htmlspecialchars($question['yes_text']); ?></span>
                                    <span class="label-tag label-no">✗ <?php echo htmlspecialchars($question['no_text']); ?></span>
                                </div>
                            </div>
                            <div class="question-actions">
                                <button class="btn btn-edit" onclick="editQuestion(<?php echo $question['id']; ?>, '<?php echo addslashes($question['text']); ?>', '<?php echo addslashes($question['yes_text']); ?>', '<?php echo addslashes($question['no_text']); ?>')">
                                    ✏️ Editar
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta pregunta?');">
                                    <input type="hidden" name="action" value="delete_question">
                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                    <button type="submit" class="btn btn-danger">🗑️ Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reset de respuestas por pregunta -->
        <div class="section">
            <h2>🔄 Reiniciar Respuestas por Pregunta</h2>
            <form method="POST" onsubmit="return confirm('¿Reiniciar las respuestas de esta pregunta en esta instancia?');">
                <input type="hidden" name="action" value="reset_answers">
                <div class="form-row">
                    <div class="form-group">
                        <label>Instancia:</label>
                        <select name="code" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($instances as $inst): ?>
                                <option value="<?php echo $inst['code']; ?>"><?php echo $inst['code']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pregunta:</label>
                        <select name="question_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($questions as $q): ?>
                                <option value="<?php echo $q['id']; ?>">ID <?php echo $q['id']; ?>: <?php echo substr($q['text'], 0, 50); ?>...</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-warning">🔄 Reiniciar Respuestas</button>
            </form>
        </div>

        <!-- Documentación de API -->
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
                
                <p style="margin-top: 15px; color: rgba(255,255,255,0.5);">
                    <strong>Ejemplo:</strong> Para activar la pregunta 1 en la instancia ABC123:<br>
                    <code style="color: #96c93d;">https://tu-dominio.com/api.php?action=go&code=ABC123&question_id=1</code>
                </p>
            </div>
        </div>
    </div>

    <!-- Modal para editar pregunta -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">✏️ Editar Pregunta</div>
            <form method="POST">
                <input type="hidden" name="action" value="update_question">
                <input type="hidden" name="question_id" id="edit_question_id">
                <div class="form-group">
                    <label>Texto de la pregunta:</label>
                    <textarea name="question_text" id="edit_question_text" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Texto botón afirmativo:</label>
                        <input type="text" name="yes_text" id="edit_yes_text" placeholder="SÍ">
                    </div>
                    <div class="form-group">
                        <label>Texto botón negativo:</label>
                        <input type="text" name="no_text" id="edit_no_text" placeholder="NO">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editQuestion(id, text, yesText, noText) {
            document.getElementById('edit_question_id').value = id;
            document.getElementById('edit_question_text').value = text;
            document.getElementById('edit_yes_text').value = yesText;
            document.getElementById('edit_no_text').value = noText;
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>