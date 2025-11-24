<?php
// admin.php
session_start();
require_once 'db.php';
$db = new Database();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_question') {
        $text = trim($_POST['question_text']);
        $timer = intval($_POST['timer_seconds'] ?? 0);
        $options = $_POST['options'] ?? [];
        // Filtrar opciones vacías
        $options = array_filter($options, function($v) { return trim($v) !== ''; });
        
        if ($text && count($options) >= 2) {
            $db->createQuestion($text, $options, $timer);
            $message = 'Pregunta creada';
        } else {
            $message = 'Error: Se requiere texto y al menos 2 opciones.';
        }
    } elseif ($action === 'create_instance') {
        try {
            $db->createSurveyInstance(strtoupper($_POST['code']));
        } catch(Exception $e) { $message = "El código ya existe"; }
    } elseif ($action === 'delete_question') {
        $db->deleteQuestion($_POST['question_id']);
    } elseif ($action === 'delete_instance') {
        $db->deleteSurveyInstance($_POST['code']);
    } elseif ($action === 'reset_all') {
        $db->resetAllAnswers($_POST['code']);
    }
}

$questions = $db->getQuestions();
$instances = $db->getSurveyInstances();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <style>
        body { background: #0a0a0f; color: #fff; font-family: sans-serif; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .box { background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #333; }
        input, select, button { padding: 10px; margin: 5px 0; border-radius: 5px; border: 1px solid #555; background: #222; color: #fff; }
        button { cursor: pointer; background: #8a2be2; border: none; font-weight: bold; }
        button:hover { opacity: 0.9; }
        .btn-danger { background: #e74c3c; }
        .btn-action { background: #3498db; margin-right: 5px; }
        .option-row { display: flex; gap: 10px; }
        .q-item { border-bottom: 1px solid #333; padding: 15px 0; }
        .tag { font-size: 0.8em; padding: 2px 8px; border-radius: 4px; background: #333; }
        .tag.timer { background: #d35400; }
    </style>
    <script>
        function addOptionField() {
            const div = document.createElement('div');
            div.className = 'option-row';
            div.innerHTML = `<input type="text" name="options[]" placeholder="Opción de respuesta" required style="flex:1"><button type="button" onclick="this.parentElement.remove()" style="background:#555">X</button>`;
            document.getElementById('options-container').appendChild(div);
        }
        function openScreen(code) {
            window.open('screen.php?code=' + code, '_blank');
        }
        function apiAction(action, code, qId) {
            fetch(`api.php?action=${action}&code=${code}&question_id=${qId}`)
                .then(r => r.json())
                .then(d => alert(d.message || 'OK'));
        }
    </script>
</head>
<body>
<div class="container">
    <h1>Panel de Control</h1>
    <?php if($message) echo "<p style='color: lime'>$message</p>"; ?>

    <div class="box">
        <h2>1. Instancias (Eventos)</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create_instance">
            <input type="text" name="code" placeholder="CÓDIGO (Ej: EVENTO24)" required>
            <button type="submit">Crear Instancia</button>
        </form>
        <div style="margin-top:10px">
            <?php foreach($instances as $inst): ?>
                <div style="display:flex; justify-content:space-between; background:rgba(0,0,0,0.3); padding:10px; margin-bottom:5px;">
                    <span><b><?= htmlspecialchars($inst['code']) ?></b></span>
                    <div>
                        <button onclick="openScreen('<?= $inst['code'] ?>')" class="btn-action">📺 Abrir Pantalla</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('¿Borrar?')">
                            <input type="hidden" name="action" value="delete_instance">
                            <input type="hidden" name="code" value="<?= $inst['code'] ?>">
                            <button class="btn-danger">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="box">
        <h2>2. Crear Pregunta</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create_question">
            <input type="text" name="question_text" placeholder="Pregunta..." style="width:100%" required>
            
            <div style="margin: 15px 0; border: 1px dashed #555; padding: 10px;">
                <label>⏱️ Temporizador (0 = Manual/Infinito):</label>
                <input type="number" name="timer_seconds" value="0" min="0" style="width: 60px"> segundos
                <br><small style="color:#aaa">Si pones segundos, la pregunta se cerrará automáticamente en las pantallas.</small>
            </div>

            <div id="options-container">
                <label>Opciones:</label>
                <div class="option-row"><input type="text" name="options[]" value="SÍ" required style="flex:1"></div>
                <div class="option-row"><input type="text" name="options[]" value="NO" required style="flex:1"></div>
            </div>
            <button type="button" onclick="addOptionField()" style="background:#444; margin-top:5px">+ Añadir Opción</button>
            <br><br>
            <button type="submit" style="width:100%">Guardar Pregunta</button>
        </form>
    </div>

    <div class="box">
        <h2>3. Gestión de Preguntas</h2>
        <?php foreach($questions as $q): ?>
            <div class="q-item">
                <div style="font-size:1.1em">
                    ID <?= $q['id'] ?>: <b><?= htmlspecialchars($q['text']) ?></b>
                    <?php if($q['timer_seconds'] > 0): ?>
                        <span class="tag timer">⏱️ <?= $q['timer_seconds'] ?>s</span>
                    <?php else: ?>
                        <span class="tag">Manual</span>
                    <?php endif; ?>
                </div>
                <div style="margin: 5px 0; color: #aaa;">
                    Opciones: <?= implode(', ', array_map(function($o){ return $o['text']; }, $q['options'])) ?>
                </div>
                <div style="margin-top:10px; display:flex; gap:10px; align-items:center;">
                    <select id="sel_<?= $q['id'] ?>">
                        <?php foreach($instances as $i): ?>
                            <option value="<?= $i['code'] ?>"><?= $i['code'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn-action" style="background:#2ecc71" onclick="apiAction('go', document.getElementById('sel_<?= $q['id'] ?>').value, <?= $q['id'] ?>)">▶ PLAY</button>
                    <button class="btn-action" style="background:#95a5a6" onclick="apiAction('off', document.getElementById('sel_<?= $q['id'] ?>').value, <?= $q['id'] ?>)">⏹ STOP</button>
                    <button class="btn-action" style="background:#f39c12" onclick="apiAction('reset', document.getElementById('sel_<?= $q['id'] ?>').value, <?= $q['id'] ?>)">🔄 Reset Votos</button>
                    
                    <form method="POST" style="margin-left:auto" onsubmit="return confirm('Eliminar?')">
                        <input type="hidden" name="action" value="delete_question">
                        <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                        <button class="btn-danger">🗑️</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
