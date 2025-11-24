<?php
// admin.php - Panel de administración actualizado
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
        $numOptions = intval($_POST['num_options'] ?? 2);
        $numOptions = max(2, min(10, $numOptions)); // Entre 2 y 10
        
        $optionLabels = [];
        for ($i = 1; $i <= $numOptions; $i++) {
            $label = trim($_POST["option_$i"] ?? "Opción $i");
            $optionLabels[] = $label;
        }
        
        $autoClose = isset($_POST['auto_close']) ? 1 : 0;
        $closeSeconds = intval($_POST['close_seconds'] ?? 30);
        $closeSeconds = max(5, min(300, $closeSeconds)); // Entre 5 y 300 segundos
        
        if ($text) {
            $db->createQuestion($text, $numOptions, json_encode($optionLabels), $autoClose, $closeSeconds);
            $message = 'Pregunta creada exitosamente';
        }
    } elseif ($action === 'update_question') {
        $id = $_POST['question_id'] ?? 0;
        $text = trim($_POST['question_text'] ?? '');
        $numOptions = intval($_POST['num_options'] ?? 2);
        $numOptions = max(2, min(10, $numOptions));
        
        $optionLabels = [];
        for ($i = 1; $i <= $numOptions; $i++) {
            $label = trim($_POST["option_$i"] ?? "Opción $i");
            $optionLabels[] = $label;
        }
        
        $autoClose = isset($_POST['auto_close']) ? 1 : 0;
        $closeSeconds = intval($_POST['close_seconds'] ?? 30);
        $closeSeconds = max(5, min(300, $closeSeconds));
        
        if ($id && $text) {
            $db->updateQuestion($id, $text, $numOptions, json_encode($optionLabels), $autoClose, $closeSeconds);
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
            max-width: 1400px;
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
        input[type="number"],
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
        input[type="number"]::placeholder,
        textarea::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border: 1px solid rgba(138, 43, 226, 0.2);
        }

        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .options-container {
            border: 1px solid rgba(138, 43, 226, 0.3);
            border-radius: 8px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
        }

        .option-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .option-number {
            min-width: 40px;
            text-align: center;
            font-weight: 700;
            color: #8a2be2;
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

        .btn-warning {
            background: linear-gradient(135deg, #f5af19 0%, #f12711 100%);
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
        }

        .btn-edit {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
            margin-right: 10px;
        }

        .btn-go {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
        }

        .btn-off {
            background: linear-gradient(135deg, #606060 0%, #404040 100%);
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
        }

        .btn-results {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
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
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .label-tag {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            background: rgba(138, 43, 226, 0.2);
            border: 1px solid rgba(138, 43, 226, 0.5);
            color: #8a2be2;
        }

        .auto-close-badge {
            background: rgba(245, 175, 25, 0.2);
            border: 1px solid rgba(245, 175, 25, 0.5);
            color: #f5af19;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            margin-top: 5px;
            display: inline-block;
        }

        .question-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .api-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .instance-select {
            padding: 8px 12px;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(138, 43, 226, 0.5);
            color: #fff;
            font-size: 0.9em;
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
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(20, 20, 30, 0.98);
            padding: 30px;
            border-radius: 15px;
            max-width: 700px;
            width: 90%;
            border: 1px solid rgba(138, 43, 226, 0.5);
            box-shadow: 0 0 60px rgba(138, 43, 226, 0.3);
            max-height: 90vh;
            overflow-y: auto;
            margin: 20px;
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

        small {
            color: rgba(255, 255, 255, 0.5);
        }

        #optionsCountDisplay {
            color: #8a2be2;
            font-weight: 700;
            font-size: 1.2em;
        }

        @media (max-width: 768px) {
            .question-header,
            .instance-header {
                flex-direction: column;
                align-items: flex-start;
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

<!-- Instancias -->
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
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Reiniciar TODAS las respuestas?');">
                                    <input type="hidden" name="action" value="reset_all_answers">
                                    <input type="hidden" name="code" value="<?php echo $instance['code']; ?>">
                                    <button type="submit" class="btn btn-warning">🔄 Reset</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta instancia?');">
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

        <!-- Gestión de Preguntas -->
        <div class="section">
            <h2>❓ Gestión de Preguntas</h2>
            <form method="POST" id="createQuestionForm">
                <input type="hidden" name="action" value="create_question">
                
                <div class="form-group">
                    <label>Texto de la pregunta:</label>
                    <textarea name="question_text" required placeholder="Escribe tu pregunta aquí..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Número de opciones: <span id="optionsCountDisplay">2</span></label>
                        <input type="range" name="num_options" id="numOptions" min="2" max="10" value="2" 
                               style="width: 100%;" oninput="updateOptionsInputs(this.value)">
                    </div>
                </div>

                <div class="form-group">
                    <label>Etiquetas de las opciones:</label>
                    <div class="options-container" id="optionsContainer">
                        <!-- Se genera dinámicamente -->
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="auto_close" id="autoClose" onchange="toggleAutoClose()">
                            <label for="autoClose" style="margin: 0;">Cierre automático</label>
                        </div>
                    </div>
                    <div class="form-group" id="closeSecondsGroup" style="display: none;">
                        <label>Segundos para cerrar:</label>
                        <input type="number" name="close_seconds" min="5" max="300" value="30" placeholder="30">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Agregar Pregunta</button>
            </form>

            <div class="question-list">
                <h3 style="color: rgba(255,255,255,0.7); margin: 20px 0 15px;">Preguntas Existentes:</h3>
                <?php foreach ($questions as $question): 
                    $labels = json_decode($question['option_labels'], true);
                ?>
                    <div class="question-item">
                        <div class="question-header">
                            <div class="question-text">
                                <strong>ID <?php echo $question['id']; ?>:</strong> 
                                <?php echo htmlspecialchars($question['text']); ?>
                                <div class="question-labels">
                                    <?php foreach ($labels as $label): ?>
                                        <span class="label-tag"><?php echo htmlspecialchars($label); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($question['auto_close']): ?>
                                    <div>
                                        <span class="auto-close-badge">
                                            ⏱️ Auto-cierre: <?php echo $question['close_seconds']; ?>s
                                        </span>
                                    </div>
                                <?php endif; ?>
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
                                    <button class="btn btn-results" onclick="openResults(document.getElementById('instance_q<?php echo $question['id']; ?>').value, <?php echo $question['id']; ?>)">Resultado visual</button>
                                    <button class="btn btn-results" onclick="openApi('results', document.getElementById('instance_q<?php echo $question['id']; ?>').value, <?php echo $question['id']; ?>)">API JSON</button>
                                    <button class="btn btn-results" onclick="openApi('results_simple', document.getElementById('instance_q<?php echo $question['id']; ?>').value, <?php echo $question['id']; ?>)">API Ganador</button>

                                </div>
                                <div style="margin-top: 10px;">
                                    <button class="btn btn-edit" onclick='editQuestion(<?php echo json_encode($question); ?>)'>✏️</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta pregunta?');">
                                        <input type="hidden" name="action" value="delete_question">
                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                        <button type="submit" class="btn btn-danger">🗑️</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal para editar -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">✏️ Editar Pregunta</div>
            <form method="POST" id="editQuestionForm">
                <input type="hidden" name="action" value="update_question">
                <input type="hidden" name="question_id" id="edit_question_id">
                
                <div class="form-group">
                    <label>Texto de la pregunta:</label>
                    <textarea name="question_text" id="edit_question_text" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Número de opciones: <span id="editOptionsCountDisplay">2</span></label>
                        <input type="range" name="num_options" id="editNumOptions" min="2" max="10" value="2" 
                               style="width: 100%;" oninput="updateEditOptionsInputs(this.value)">
                    </div>
                </div>

                <div class="form-group">
                    <label>Etiquetas de las opciones:</label>
                    <div class="options-container" id="editOptionsContainer"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="auto_close" id="editAutoClose" onchange="toggleEditAutoClose()">
                            <label for="editAutoClose" style="margin: 0;">Cierre automático</label>
                        </div>
                    </div>
                    <div class="form-group" id="editCloseSecondsGroup" style="display: none;">
                        <label>Segundos para cerrar:</label>
                        <input type="number" name="close_seconds" id="editCloseSeconds" min="5" max="300" value="30">
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
        function openApi(action, code, questionId) {
            const url = `api.php?action=${action}&code=${code}&question_id=${questionId}`;
            window.open(url, '_blank');
        }

        function openResults(code, questionId) {
            const url = `results.php?code=${code}&question_id=${questionId}`;
            window.open(url, '_blank');
        }

        function updateOptionsInputs(num) {
            document.getElementById('optionsCountDisplay').textContent = num;
            const container = document.getElementById('optionsContainer');
            container.innerHTML = '';
            
            for (let i = 1; i <= num; i++) {
                const div = document.createElement('div');
                div.className = 'option-input-group';
                div.innerHTML = `
                    <span class="option-number">${i}</span>
                    <input type="text" name="option_${i}" placeholder="Opción ${i}" required>
                `;
                container.appendChild(div);
            }
        }

        function updateEditOptionsInputs(num) {
            document.getElementById('editOptionsCountDisplay').textContent = num;
            const container = document.getElementById('editOptionsContainer');
            const currentLabels = [];
            
            // Guardar valores actuales
            container.querySelectorAll('input').forEach(input => {
                currentLabels.push(input.value);
            });
            
            container.innerHTML = '';
            
            for (let i = 1; i <= num; i++) {
                const div = document.createElement('div');
                div.className = 'option-input-group';
                const value = currentLabels[i-1] || `Opción ${i}`;
                div.innerHTML = `
                    <span class="option-number">${i}</span>
                    <input type="text" name="option_${i}" placeholder="Opción ${i}" value="${value}" required>
                `;
                container.appendChild(div);
            }
        }

        function toggleAutoClose() {
            const checked = document.getElementById('autoClose').checked;
            document.getElementById('closeSecondsGroup').style.display = checked ? 'block' : 'none';
        }

        function toggleEditAutoClose() {
            const checked = document.getElementById('editAutoClose').checked;
            document.getElementById('editCloseSecondsGroup').style.display = checked ? 'block' : 'none';
        }

        function editQuestion(question) {
            document.getElementById('edit_question_id').value = question.id;
            document.getElementById('edit_question_text').value = question.text;
            document.getElementById('editNumOptions').value = question.num_options;
            document.getElementById('editAutoClose').checked = question.auto_close == 1;
            document.getElementById('editCloseSeconds').value = question.close_seconds;
            
            toggleEditAutoClose();
            
            const labels = JSON.parse(question.option_labels);
            updateEditOptionsInputs(question.num_options);
            
            // Llenar los valores
            labels.forEach((label, index) => {
                const input = document.querySelector(`#editOptionsContainer input[name="option_${index+1}"]`);
                if (input) input.value = label;
            });
            
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // Inicializar formulario de creación
        updateOptionsInputs(2);
    </script>
</body>
</html>
