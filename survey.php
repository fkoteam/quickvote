<?php
// survey.php - Interfaz de participante
session_start();
require_once 'db.php';

$db = new Database();
$code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';

if (!$code || !$db->surveyExists($code)) {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['participant_id'])) {
    $_SESSION['participant_id'] = uniqid('part_', true);
}
$participantId = $_SESSION['participant_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($code); ?></title>
    <style>
        /* ESTILOS ORIGINALES CONSERVADOS */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #000; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; overflow: hidden; }
        #stars-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; }
        .container { background: rgba(20, 20, 30, 0.65); padding: 50px 40px; border-radius: 25px; box-shadow: 0 0 60px rgba(138, 43, 226, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.1); text-align: center; max-width: 700px; width: 100%; animation: fadeIn 0.5s ease; position: relative; z-index: 1; border: 1px solid rgba(138, 43, 226, 0.3); }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        #status { font-size: 1.8em; color: rgba(255, 255, 255, 0.5); margin: 30px 0; min-height: 60px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .waiting-text { margin-top: 20px; font-size: 0.6em; color: rgba(255, 255, 255, 0.4); }
        .loader { border: 4px solid rgba(138, 43, 226, 0.2); border-top: 4px solid #8a2be2; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .question-box { display: none; animation: slideIn 0.5s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .question-box.active { display: block; }
        .question-text { font-size: 2em; color: #fff; margin-bottom: 40px; font-weight: 600; line-height: 1.4; text-shadow: 0 0 30px rgba(138, 43, 226, 0.3); }
        .answered { background: linear-gradient(135deg, rgba(0, 176, 155, 0.2) 0%, rgba(150, 201, 61, 0.2) 100%); border: 1px solid rgba(0, 176, 155, 0.5); color: #96c93d; padding: 20px; border-radius: 12px; margin-top: 30px; font-size: 1.2em; animation: fadeIn 0.5s ease; }
        
        /* NUEVOS ESTILOS PARA SOPORTAR N OPCIONES Y TIMER */
        .buttons-grid { display: flex; flex-direction: column; gap: 15px; margin-top: 30px; }
        .btn-option {
            width: 100%; padding: 20px; font-size: 1.3em; font-weight: 700; border: none; border-radius: 15px; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 1px;
            /* Estilo neutro pero brillante, similar al tema */
            background: linear-gradient(135deg, rgba(60, 60, 80, 0.8) 0%, rgba(40, 40, 60, 0.8) 100%);
            border: 1px solid rgba(138, 43, 226, 0.3); color: white;
        }
        .btn-option:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(138, 43, 226, 0.3); border-color: #8a2be2; }
        .btn-option:active { transform: translateY(0); }
        .btn-option:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-option.selected { background: linear-gradient(135deg, #8a2be2 0%, #4a0080 100%); border-color: #fff; box-shadow: 0 0 20px rgba(138, 43, 226, 0.6); }

        /* BARRA DE PROGRESO */
        .timer-container { width: 100%; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; margin-bottom: 25px; overflow: hidden; display: none; }
        .timer-bar { height: 100%; background: linear-gradient(90deg, #00b09b, #96c93d); width: 100%; transition: width 0.1s linear; }
        .timer-bar.urgent { background: linear-gradient(90deg, #eb3349, #f45c43); }
    </style>
</head>
<body>
    <canvas id="stars-canvas"></canvas>
    
    <div class="container">
        <div id="status">
            <div class="loader"></div>
            <div class="waiting-text">Esperando pregunta...</div>
        </div>
        
        <div id="questionBox" class="question-box">
            <!-- Barra de tiempo -->
            <div id="timerContainer" class="timer-container">
                <div id="timerBar" class="timer-bar"></div>
            </div>

            <div class="question-text" id="questionText"></div>
            
            <!-- Contenedor dinámico de botones -->
            <div class="buttons-grid" id="optionsContainer"></div>
            
            <div id="answeredMsg"></div>
        </div>
    </div>

    <script>
        const surveyCode = '<?php echo $code; ?>';
        const participantId = '<?php echo $participantId; ?>';
        let currentQuestionId = null;
        let hasAnswered = false;
        let timerInterval = null;

        // Wake Lock
        let wakeLock = null;
        async function requestWakeLock() {
            if ('wakeLock' in navigator) {
                try { wakeLock = await navigator.wakeLock.request('screen'); } catch (err) {}
            } else { startNoSleepFallback(); }
        }
        document.addEventListener('visibilitychange', async () => { if (document.visibilityState === 'visible') await requestWakeLock(); });
        function startNoSleepFallback() { /* Fallback video oculto */ }
        requestWakeLock();

        // Polling
        function checkQuestion() {
            fetch('api.php?action=check&code=' + surveyCode)
                .then(response => response.json())
                .then(data => {
                    if (data.question_id) {
                        // Si cambia la pregunta
                        if (data.question_id !== currentQuestionId) {
                            currentQuestionId = data.question_id;
                            hasAnswered = false;
                            showQuestion(data);
                        }
                        // Actualizar Timer siempre
                        updateTimer(data.timer_total, data.timer_remaining);
                    } else if (!data.question_id && currentQuestionId) {
                        currentQuestionId = null;
                        hideQuestion();
                    }
                })
                .catch(err => console.error('Error:', err));
        }

        function showQuestion(data) {
            document.getElementById('status').style.display = 'none';
            document.getElementById('questionText').textContent = data.question_text;
            document.getElementById('questionBox').classList.add('active');
            document.getElementById('answeredMsg').innerHTML = '';
            
            // Generar botones dinámicos
            const container = document.getElementById('optionsContainer');
            container.innerHTML = '';
            
            data.options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className = 'btn-option';
                btn.textContent = opt.text;
                btn.onclick = () => answer(opt.option_index, btn);
                container.appendChild(btn);
            });
        }

        function updateTimer(total, remaining) {
            const container = document.getElementById('timerContainer');
            const bar = document.getElementById('timerBar');
            
            if (total > 0 && remaining !== null) {
                container.style.display = 'block';
                const percentage = (remaining / total) * 100;
                bar.style.width = percentage + '%';
                
                if (percentage < 30) bar.classList.add('urgent');
                else bar.classList.remove('urgent');

                if (remaining <= 0) {
                    disableButtons();
                    if (!hasAnswered) {
                         document.getElementById('answeredMsg').innerHTML = '<div class="answered" style="color:#f45c43; border-color:#f45c43; background:rgba(235,51,73,0.1)">⌛ Tiempo agotado</div>';
                    }
                }
            } else {
                container.style.display = 'none';
            }
        }

        function hideQuestion() {
            document.getElementById('status').style.display = 'flex';
            document.getElementById('questionBox').classList.remove('active');
            clearInterval(timerInterval);
        }

        function answer(index, btnElement) {
            if (hasAnswered) return;
            
            // UI Feedback
            const allBtns = document.querySelectorAll('.btn-option');
            allBtns.forEach(b => b.style.opacity = '0.5');
            btnElement.style.opacity = '1';
            btnElement.classList.add('selected');
            disableButtons();

            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=answer&code=${surveyCode}&question_id=${currentQuestionId}&participant_id=${participantId}&answer=${index}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    hasAnswered = true;
                    document.getElementById('answeredMsg').innerHTML = '<div class="answered">✓ Respuesta registrada</div>';
                }
            });
        }

        function disableButtons() {
            document.querySelectorAll('.btn-option').forEach(btn => btn.disabled = true);
        }

        setInterval(checkQuestion, 1000);
        checkQuestion();

        // ESTRELLAS (Conservado del original)
        const canvas = document.getElementById('stars-canvas');
        const ctx = canvas.getContext('2d');
        let stars = [];
        const numStars = 150;
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        function createStars() {
            stars = [];
            for (let i = 0; i < numStars; i++) stars.push({ x: Math.random() * canvas.width, y: Math.random() * canvas.height, radius: Math.random() * 1.5 + 0.5, opacity: Math.random() * 0.5 + 0.2, speed: Math.random() * 0.2 + 0.05, twinkle: Math.random() * 0.015 });
        }
        function drawStars() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            stars.forEach(star => {
                star.opacity += star.twinkle;
                if (star.opacity > 0.7 || star.opacity < 0.2) star.twinkle = -star.twinkle;
                star.y -= star.speed;
                if (star.y < 0) { star.y = canvas.height; star.x = Math.random() * canvas.width; }
                ctx.beginPath(); ctx.arc(star.x, star.y, star.radius, 0, Math.PI * 2); ctx.fillStyle = `rgba(255, 255, 255, ${star.opacity})`; ctx.fill();
            });
            requestAnimationFrame(drawStars);
        }
        window.addEventListener('resize', () => { resizeCanvas(); createStars(); });
        resizeCanvas(); createStars(); drawStars();
    </script>
</body>
</html>
