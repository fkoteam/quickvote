<?php
// survey.php - Interfaz de participante con actualización en tiempo real
session_start();
require_once 'db.php';

$db = new Database();
$code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';

if (!$code || !$db->surveyExists($code)) {
    header('Location: index.php');
    exit;
}

// Generar ID de sesión único para este participante
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
    <title>Encuesta - <?php echo htmlspecialchars($code); ?></title>
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
            max-width: 700px;
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
            margin-bottom: 40px;
            font-weight: 600;
            line-height: 1.4;
            text-shadow: 0 0 30px rgba(138, 43, 226, 0.3);
        }

        .buttons {
            display: flex;
            gap: 20px;
            margin-top: 40px;
        }

        .btn {
            flex: 1;
            padding: 30px 20px;
            font-size: 1.5em;
            font-weight: 700;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .btn-yes {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(0, 176, 155, 0.4);
        }

        .btn-yes:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 176, 155, 0.5);
        }

        .btn-no {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(235, 51, 73, 0.4);
        }

        .btn-no:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(235, 51, 73, 0.5);
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

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }

            .question-text {
                font-size: 1.5em;
            }

            .buttons {
                flex-direction: column;
            }

            .btn {
                font-size: 1.3em;
                padding: 25px 20px;
            }
        }
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
            <div class="question-text" id="questionText"></div>
            <div class="buttons">
                <button class="btn btn-yes" id="btnYes" onclick="answer('yes')"></button>
                <button class="btn btn-no" id="btnNo" onclick="answer('no')"></button>
            </div>
            <div id="answeredMsg"></div>
        </div>
    </div>

    <script>
        const surveyCode = '<?php echo $code; ?>';
        const participantId = '<?php echo $participantId; ?>';
        let currentQuestionId = null;
        let hasAnswered = false;

        // ============================================
        // WAKE LOCK API - Mantener pantalla encendida
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
                // Fallback para navegadores sin Wake Lock API
                startNoSleepFallback();
            }
        }

        // Reactivar Wake Lock cuando la página vuelva a ser visible
        document.addEventListener('visibilitychange', async () => {
            if (document.visibilityState === 'visible') {
                await requestWakeLock();
            }
        });

        // Fallback: reproducir video invisible para mantener pantalla activa
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
            
            // Video mínimo en base64 (1x1 pixel, transparente)
            video.src = 'data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAAA0htZGF0AAACrgYF//+q3EXpvebZSLeWLNgg2SPu73gyNjQgLSBjb3JlIDE0MiByMjQ3OSBkZDc5YTYxIC0gSC4yNjQvTVBFRy00IEFWQyBjb2RlYyAtIENvcHlsZWZ0IDIwMDMtMjAxNCAtIGh0dHA6Ly93d3cudmlkZW9sYW4ub3JnL3gyNjQuaHRtbCAtIG9wdGlvbnM6IGNhYmFjPTEgcmVmPTMgZGVibG9jaz0xOjA6MCBhbmFseXNlPTB4MzoweDExMyBtZT1oZXggc3VibWU9NyBwc3k9MSBwc3lfcmQ9MS4wMDowLjAwIG1peGVkX3JlZj0xIG1lX3JhbmdlPTE2IGNocm9tYV9tZT0xIHRyZWxsaXM9MSA4eDhkY3Q9MSBjcW09MCBkZWFkem9uZT0yMSwxMSBmYXN0X3Bza2lwPTEgY2hyb21hX3FwX29mZnNldD0tMiB0aHJlYWRzPTEgbG9va2FoZWFkX3RocmVhZHM9MSBzbGljZWRfdGhyZWFkcz0wIG5yPTAgZGVjaW1hdGU9MSBpbnRlcmxhY2VkPTAgYmx1cmF5X2NvbXBhdD0wIGNvbnN0cmFpbmVkX2ludHJhPTAgYmZyYW1lcz0zIGJfcHlyYW1pZD0yIGJfYWRhcHQ9MSBiX2JpYXM9MCBkaXJlY3Q9MSB3ZWlnaHRiPTEgb3Blbl9nb3A9MCB3ZWlnaHRwPTIga2V5aW50PTI1MCBrZXlpbnRfbWluPTEgc2NlbmVjdXQ9NDAgaW50cmFfcmVmcmVzaD0wIHJjX2xvb2thaGVhZD00MCByYz1jcmYgbWJ0cmVlPTEgY3JmPTIzLjAgcWNvbXA9MC42MCBxcG1pbj0wIHFwbWF4PTY5IHFwc3RlcD00IGlwX3JhdGlvPTEuNDAgYXE9MToxLjAwAIAAAAFPZYiEABD//veBvzLLXyK6UXSICoECgiYAAADAAADAAADAAAMALLHiAAAAKG1vb3YAAABsbXZoZAAAAAAAAAAAAAAAAAAAA+gAAAAoAAEAAAEAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAIAAAB0dHJhawAAAFx0a2hkAAAAAwAAAAAAAAAAAAAAAQAAAAAAAAAoAAAAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAQAAAAAAQAAAAEAAAAAAAJGVkdHMAAAAcZWxzdAAAAAAAAAABAAAAAygAAAAAAAEAAAAAAOxtZGlhAAAAIG1kaGQAAAAAAAAAAAAAAAAAACgAAAAoAFXEAAAAAAAtaGRscgAAAAAAAAAAdmlkZQAAAAAAAAAAAAAAAFZpZGVvSGFuZGxlcgAAAACXbWluZgAAABR2bWhkAAAAAQAAAAAAAAAAAAAAJGRpbmYAAAAcZHJlZgAAAAAAAAABAAAADHVybCAAAAABAAAAV3N0YmwAAABXc3RzZAAAAAAAAAABAAAAR2F2YzEAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAAAEAAQAEgAAABIAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAY//8AAAAxYXZjQwFkAAr/4QAYZ2QACqzZQo35IQAAAwAEAAADAMg8SJZYAQAGaOvjyyLA/fj4AAAAABRidHJ0AAAAAAAA6AAAAOgAAAAYc3R0cwAAAAAAAAABAAAAFAAA';
            
            document.body.appendChild(video);
            video.play().catch(() => {});
        }

        // Iniciar Wake Lock al cargar
        requestWakeLock();

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
                        showQuestion(data.question_text, data.yes_text, data.no_text);
                    } else if (!data.question_id && currentQuestionId) {
                        currentQuestionId = null;
                        hideQuestion();
                    }
                })
                .catch(err => console.error('Error:', err));
        }

        function showQuestion(text, yesText, noText) {
            document.getElementById('status').style.display = 'none';
            document.getElementById('questionText').textContent = text;
            document.getElementById('btnYes').textContent = yesText;
            document.getElementById('btnNo').textContent = noText;
            document.getElementById('questionBox').classList.add('active');
            document.getElementById('answeredMsg').innerHTML = '';
            document.querySelectorAll('.btn').forEach(btn => btn.disabled = false);
        }

        function hideQuestion() {
            document.getElementById('status').style.display = 'flex';
            document.getElementById('questionBox').classList.remove('active');
        }

        function answer(response) {
            if (hasAnswered) return;
            
            document.querySelectorAll('.btn').forEach(btn => btn.disabled = true);
            
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=answer&code=${surveyCode}&question_id=${currentQuestionId}&participant_id=${participantId}&answer=${response}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    hasAnswered = true;
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
