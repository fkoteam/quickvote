<?php
// survey.php - Interfaz de participante actualizada con temporizador
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #000;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

        #stars-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .container {
            background: rgba(20, 20, 30, 0.65);
            padding: 50px 40px;
            border-radius: 25px;
            box-shadow: 0 0 60px rgba(138, 43, 226, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
            text-align: center;
            max-width: 800px;
            width: 100%;
            animation: fadeIn 0.5s ease;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(138, 43, 226, 0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .code-badge {
            display: inline-block;
            background: linear-gradient(135deg, #8a2be2 0%, #4a0080 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 1.2em;
            font-weight: 700;
            letter-spacing: 3px;
            margin-bottom: 40px;
            box-shadow: 0 5px 20px rgba(138, 43, 226, 0.5);
        }

        #status {
            font-size: 1.8em;
            color: rgba(255, 255, 255, 0.5);
            margin: 30px 0;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .waiting-text {
            margin-top: 20px;
            font-size: 0.6em;
            color: rgba(255, 255, 255, 0.4);
        }

        .loader {
            border: 4px solid rgba(138, 43, 226, 0.2);
            border-top: 4px solid #8a2be2;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .question-box {
            display: none;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .question-box.active {
            display: block;
        }

        .question-text {
            font-size: 2em;
            color: #fff;
            margin-bottom: 30px;
            font-weight: 600;
            line-height: 1.4;
            text-shadow: 0 0 30px rgba(138, 43, 226, 0.3);
        }
/* Timer Container - Estilo Base */
.timer-container {
    margin-bottom: 40px; /* Más aire alrededor */
    opacity: 0;
    transition: opacity 0.6s ease; /* Transición más suave y lenta */
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; /* Fuente limpia */
}

.timer-container.visible {
    opacity: 1;
}

/* Información (Texto) */
.timer-info {
    display: flex;
    justify-content: space-between;
    align-items: flex-end; /* Alineado abajo para elegancia */
    margin-bottom: 12px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.85em;
    letter-spacing: 2px; /* Espaciado "premium" */
    text-transform: uppercase;
}

.timer-label {
    font-weight: 300; /* Letra fina */
    opacity: 0.7;
}

.timer-value {
    font-size: 1.4em;
    font-weight: 300;
    font-variant-numeric: tabular-nums; /* Evita que los números bailen */
    color: #ffffff;
    text-shadow: 0 0 10px rgba(255, 255, 255, 0.3); /* Brillo sutil */
    transition: color 0.5s ease;
}

/* Estados del texto (Sobrios) */
.timer-value.warning {
    color: rgba(255, 255, 255, 0.8);
}

.timer-value.danger {
    color: rgba(255, 255, 255, 0.6);
    animation: breathe 2s ease-in-out infinite; /* Animación lenta */
}

/* Contenedor de la barra (El "Raíl") */
.timer-bar-container {
    width: 100%;
    height: 6px; /* Mucho más fina y elegante */
    background: rgba(255, 255, 255, 0.1); /* Transparencia sutil */
    border-radius: 4px;
    /* overflow: visible; Visible para que se vea el brillo exterior */
    overflow: hidden; 
    border: none; /* Sin bordes duros */
    /* Efecto cristal (opcional, depende del navegador) */
    backdrop-filter: blur(5px); 
    -webkit-backdrop-filter: blur(5px);
}

/* La Barra de Progreso */
.timer-bar {
    height: 100%;
    background: #ffffff; /* Blanco puro */
    border-radius: 4px;
    transition: width 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94); /* Movimiento fluido */
    position: relative;
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.4); /* Resplandor elegante */
}

/* Eliminamos el shimmer barato y usamos un brillo sutil en la punta */
.timer-bar::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 100px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
    opacity: 0.5;
    transform: translateX(50%); /* Se mantiene en la punta derecha */
}

/* Estados de la barra (Monocromáticos) */

/* Warning: La barra se vuelve ligeramente translúcida */
.timer-bar.warning {
    background: rgba(255, 255, 255, 0.7);
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
}

/* Danger: La barra parpadea lentamente ("respiración") y se atenúa */
.timer-bar.danger {
    background: rgba(255, 255, 255, 0.5);
    box-shadow: none;
    animation: breathe-bar 2s infinite;
}

/* Animaciones refinadas */

/* Respiración para el texto */
@keyframes breathe {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Respiración para la barra (más sutil) */
@keyframes breathe-bar {
    0%, 100% { opacity: 1; box-shadow: 0 0 5px rgba(255,255,255,0.1); }
    50% { opacity: 0.3; box-shadow: 0 0 0 rgba(255,255,255,0); }
}

        /* Botones dinámicos */
        .buttons {
            display: grid;
            gap: 15px;
            margin-top: 30px;
        }

        .buttons.cols-2 { grid-template-columns: repeat(2, 1fr); }
        .buttons.cols-3 { grid-template-columns: repeat(3, 1fr); }
        .buttons.cols-4 { grid-template-columns: repeat(2, 1fr); }
        .buttons.cols-5 { grid-template-columns: repeat(3, 1fr); }

        .btn {
            padding: 25px 20px;
            font-size: 1.3em;
            font-weight: 700;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn span {
            position: relative;
            z-index: 1;
        }

        .btn-1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-2 {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            box-shadow: 0 10px 30px rgba(240, 147, 251, 0.4);
        }

        .btn-3 {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            box-shadow: 0 10px 30px rgba(79, 172, 254, 0.4);
        }

        .btn-4 {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            box-shadow: 0 10px 30px rgba(67, 233, 123, 0.4);
        }

        .btn-5 {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            box-shadow: 0 10px 30px rgba(250, 112, 154, 0.4);
        }

        .btn-6 {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            box-shadow: 0 10px 30px rgba(48, 207, 208, 0.4);
        }

        .btn-7 {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            box-shadow: 0 10px 30px rgba(168, 237, 234, 0.4);
        }

        .btn-8 {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            box-shadow: 0 10px 30px rgba(255, 154, 158, 0.4);
        }

        .btn-9 {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            box-shadow: 0 10px 30px rgba(255, 236, 210, 0.4);
        }

        .btn-10 {
            background: linear-gradient(135deg, #ff6e7f 0%, #bfe9ff 100%);
            box-shadow: 0 10px 30px rgba(255, 110, 127, 0.4);
        }

        .btn:hover {
            transform: translateY(-5px);
            filter: brightness(1.1);
        }

        .btn:active {
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .answered {
            background: linear-gradient(135deg, rgba(0, 176, 155, 0.2) 0%, rgba(150, 201, 61, 0.2) 100%);
            border: 1px solid rgba(0, 176, 155, 0.5);
            color: #96c93d;
            padding: 20px;
            border-radius: 12px;
            margin-top: 30px;
            font-size: 1.2em;
            animation: fadeIn 0.5s ease;
        }

        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
            }

            .question-text {
                font-size: 1.5em;
            }

            .buttons {
                gap: 10px;
            }

            .buttons.cols-3,
            .buttons.cols-4,
            .buttons.cols-5 {
                grid-template-columns: repeat(2, 1fr);
            }

            .btn {
                font-size: 1.1em;
                padding: 20px 15px;
            }
        }

        @media (max-width: 480px) {
            .buttons.cols-2,
            .buttons.cols-3,
            .buttons.cols-4,
            .buttons.cols-5 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <canvas id="stars-canvas"></canvas>
    
    <div class="container">
        <div class="code-badge"><?php echo htmlspecialchars($code); ?></div>
        
        <div id="status">
            <div class="loader"></div>
            <div class="waiting-text">Esta pantalla se actualizará automáticamente...</div>
        </div>
        
        <div id="questionBox" class="question-box">
            <div class="question-text" id="questionText"></div>
            
            <div class="timer-container" id="timerContainer">
                <div class="timer-info">
                    <span class="timer-label"><!--⏱️ Tiempo restante:--></span>
                    <span class="timer-value" id="timerValue">--</span>
                </div>
                <div class="timer-bar-container">
                    <div class="timer-bar" id="timerBar"></div>
                </div>
            </div>
            
            <div class="buttons" id="buttonsContainer"></div>
            
            <div id="answeredMsg"></div>
        </div>
    </div>
<script>
        const surveyCode = '<?php echo $code; ?>';
        const participantId = '<?php echo $participantId; ?>';
        let currentQuestionId = null;
        let hasAnswered = false;
        let timerInterval = null;
        let totalSeconds = 0;
        let remainingSeconds = 0;

        // ============================================
        // WAKE LOCK API
        // ============================================
        let wakeLock = null;

        async function requestWakeLock() {
            if ('wakeLock' in navigator) {
                try {
                    wakeLock = await navigator.wakeLock.request('screen');
                    console.log('Wake Lock activado');
                    wakeLock.addEventListener('release', () => {
                        console.log('Wake Lock liberado');
                    });
                } catch (err) {
                    console.log('Wake Lock error:', err.message);
                }
            } else {
                startNoSleepFallback();
            }
        }

        document.addEventListener('visibilitychange', async () => {
            if (document.visibilityState === 'visible') {
                await requestWakeLock();
            }
        });

        function startNoSleepFallback() {
            const video = document.createElement('video');
            video.setAttribute('playsinline', '');
            video.setAttribute('muted', '');
            video.setAttribute('loop', '');
            video.style.position = 'fixed';
            video.style.top = '-1000px';
            video.style.left = '-1000px';
            video.style.width = '1px';
            video.style.height = '1px';
            video.src = 'data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAAA0htZGF0AAACrgYF//+q3EXpvebZSLeWLNgg2SPu73gyNjQgLSBjb3JlIDE0MiByMjQ3OSBkZDc5YTYxIC0gSC4yNjQvTVBFRy00IEFWQyBjb2RlYyAtIENvcHlsZWZ0IDIwMDMtMjAxNCAtIGh0dHA6Ly93d3cudmlkZW9sYW4ub3JnL3gyNjQuaHRtbCAtIG9wdGlvbnM6IGNhYmFjPTEgcmVmPTMgZGVibG9jaz0xOjA6MCBhbmFseXNlPTB4MzoweDExMyBtZT1oZXggc3VibWU9NyBwc3k9MSBwc3lfcmQ9MS4wMDowLjAwIG1peGVkX3JlZj0xIG1lX3JhbmdlPTE2IGNocm9tYV9tZT0xIHRyZWxsaXM9MSA4eDhkY3Q9MSBjcW09MCBkZWFkem9uZT0yMSwxMSBmYXN0X3Bza2lwPTEgY2hyb21hX3FwX29mZnNldD0tMiB0aHJlYWRzPTEgbG9va2FoZWFkX3RocmVhZHM9MSBzbGljZWRfdGhyZWFkcz0wIG5yPTAgZGVjaW1hdGU9MSBpbnRlcmxhY2VkPTAgYmx1cmF5X2NvbXBhdD0wIGNvbnN0cmFpbmVkX2ludHJhPTAgYmZyYW1lcz0zIGJfcHlyYW1pZD0yIGJfYWRhcHQ9MSBiX2JpYXM9MCBkaXJlY3Q9MSB3ZWlnaHRiPTEgb3Blbl9nb3A9MCB3ZWlnaHRwPTIga2V5aW50PTI1MCBrZXlpbnRfbWluPTEgc2NlbmVjdXQ9NDAgaW50cmFfcmVmcmVzaD0wIHJjX2xvb2thaGVhZD00MCByYz1jcmYgbWJ0cmVlPTEgY3JmPTIzLjAgcWNvbXA9MC42MCBxcG1pbj0wIHFwbWF4PTY5IHFwc3RlcD00IGlwX3JhdGlvPTEuNDAgYXE9MToxLjAwAIAAAAFPZYiEABD//veBvzLLXyK6UXSICoECgiYAAADAAADAAADAAAMALLHiAAAAKG1vb3YAAABsbXZoZAAAAAAAAAAAAAAAAAAAA+gAAAAoAAEAAAEAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAIAAAB0dHJhawAAAFx0a2hkAAAAAwAAAAAAAAAAAAAAAQAAAAAAAAAoAAAAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAQAAAAAAQAAAAEAAAAAAAJGVkdHMAAAAcZWxzdAAAAAAAAAABAAAAAygAAAAAAAEAAAAAAOxtZGlhAAAAIG1kaGQAAAAAAAAAAAAAAAAAACgAAAAoAFXEAAAAAAAtaGRscgAAAAAAAAAAdmlkZQAAAAAAAAAAAAAAAFZpZGVvSGFuZGxlcgAAAACXbWluZgAAABR2bWhkAAAAAQAAAAAAAAAAAAAAJGRpbmYAAAAcZHJlZgAAAAAAAAABAAAADHVybCAAAAABAAAAV3N0YmwAAABXc3RzZAAAAAAAAAABAAAAR2F2YzEAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAAAEAAQAEgAAABIAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAY//8AAAAxYXZjQwFkAAr/4QAYZ2QACqzZQo35IQAAAwAEAAADAMg8SJZYAQAGaOvjyyLA/fj4AAAAABRidHJ0AAAAAAAA6AAAAOgAAAAYc3R0cwAAAAAAAAABAAAAFAAA';
            document.body.appendChild(video);
            video.play().catch(() => {});
        }

        requestWakeLock();

        // ============================================
        // FUNCIONES DE TIMER
        // ============================================
        function startTimer(seconds) {
            stopTimer();
            totalSeconds = seconds;
            remainingSeconds = seconds;
            
            const timerContainer = document.getElementById('timerContainer');
            const timerValue = document.getElementById('timerValue');
            const timerBar = document.getElementById('timerBar');
            
            timerContainer.classList.add('visible');
            
            timerInterval = setInterval(() => {
                remainingSeconds=remainingSeconds-0.050;
                
                if (remainingSeconds <= 0) {
                    stopTimer();
                    timerValue.textContent = '0s';
                    timerBar.style.width = '0%';
                    return;
                }
                
                updateTimerDisplay();
            }, 50);
            
            updateTimerDisplay();
        }

        function updateTimerDisplay() {
            const timerValue = document.getElementById('timerValue');
            const timerBar = document.getElementById('timerBar');
            
            timerValue.textContent = Math.ceil(remainingSeconds) + 's';
            
            const percentage = (remainingSeconds / totalSeconds) * 100;
            timerBar.style.width = percentage + '%';
            
            // Cambiar colores según tiempo restante
            timerValue.classList.remove('warning', 'danger');
            timerBar.classList.remove('warning', 'danger');
            
            if (remainingSeconds <= 5) {
                timerValue.classList.add('danger');
                timerBar.classList.add('danger');
            } else if (remainingSeconds <= 10) {
                timerValue.classList.add('warning');
                timerBar.classList.add('warning');
            }
        }

        function stopTimer() {
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            
            const timerContainer = document.getElementById('timerContainer');
            timerContainer.classList.remove('visible');
        }

        // ============================================
        // POLLING Y LÓGICA DE ENCUESTA
        // ============================================
        function checkQuestion() {
            fetch('api.php?action=check&code=' + surveyCode)
                .then(response => response.json())
                .then(data => {
                    if (data.question_id && data.question_id !== currentQuestionId) {
                        currentQuestionId = data.question_id;
                        hasAnswered = false;
                        showQuestion(data);
                    } else if (!data.question_id && currentQuestionId) {
                        currentQuestionId = null;
                        hideQuestion();
                    } else if (data.question_id && data.auto_close && data.time_remaining !== null) {
                        // Actualizar timer si existe
                        if (remainingSeconds !== data.time_remaining) {
                            remainingSeconds = data.time_remaining;
                            updateTimerDisplay();
                        }
                    }
                })
                .catch(err => console.error('Error:', err));
        }

        function showQuestion(data) {
            document.getElementById('status').style.display = 'none';
            document.getElementById('questionText').textContent = data.question_text;
            
            // Crear botones dinámicamente
            const buttonsContainer = document.getElementById('buttonsContainer');
            buttonsContainer.innerHTML = '';
            
            // Determinar clase de grid según número de opciones
            let gridClass = 'cols-2';
            if (data.num_options >= 3) gridClass = 'cols-3';
            if (data.num_options >= 6) gridClass = 'cols-4';
            if (data.num_options >= 9) gridClass = 'cols-5';
            buttonsContainer.className = 'buttons ' + gridClass;
            
            for (let i = 1; i <= data.num_options; i++) {
                const btn = document.createElement('button');
                btn.className = 'btn btn-' + i;
                btn.onclick = () => answer(i);
                btn.innerHTML = '<span>' + data.option_labels[i - 1] + '</span>';
                buttonsContainer.appendChild(btn);
            }
            
            // Mostrar timer si es auto-close
            if (data.auto_close && data.time_remaining) {
                startTimer(data.time_remaining);
            } else {
                stopTimer();
            }
            
            document.getElementById('answeredMsg').innerHTML = '';
            document.getElementById('questionBox').classList.add('active');
        }

        function hideQuestion() {
            stopTimer();
            document.getElementById('status').style.display = 'flex';
            document.getElementById('questionBox').classList.remove('active');
        }

        function answer(response) {
            if (hasAnswered) return;
            
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => btn.disabled = true);
            
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=answer&code=${surveyCode}&question_id=${currentQuestionId}&participant_id=${participantId}&answer=${response}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    hasAnswered = true;
                    stopTimer();
                    document.getElementById('answeredMsg').innerHTML = '<div class="answered">✓ Respuesta registrada</div>';
                }
            })
            .catch(err => console.error('Error:', err));
        }

        // Polling cada 1 segundo
        setInterval(checkQuestion, 1000);
        checkQuestion();

        // ============================================
        // ANIMACIÓN DE ESTRELLAS
        // ============================================
        const canvas = document.getElementById('stars-canvas');
        const ctx = canvas.getContext('2d');
        let stars = [];
        const numStars = 150;

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }

        function createStars() {
            stars = [];
            for (let i = 0; i < numStars; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    radius: Math.random() * 1.5 + 0.5,
                    opacity: Math.random() * 0.5 + 0.2,
                    speed: Math.random() * 0.2 + 0.05,
                    twinkle: Math.random() * 0.015
                });
            }
        }

        function drawStars() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            stars.forEach(star => {
                star.opacity += star.twinkle;
                if (star.opacity > 0.7 || star.opacity < 0.2) {
                    star.twinkle = -star.twinkle;
                }

                star.y -= star.speed;
                if (star.y < 0) {
                    star.y = canvas.height;
                    star.x = Math.random() * canvas.width;
                }

                ctx.beginPath();
                ctx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(255, 255, 255, ${star.opacity})`;
                ctx.fill();
            });

            requestAnimationFrame(drawStars);
        }

        window.addEventListener('resize', () => {
            resizeCanvas();
            createStars();
        });

        resizeCanvas();
        createStars();
        drawStars();
    </script>
</body>
</html>

