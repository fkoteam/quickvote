<?php
// survey.php
session_start();
require_once 'db.php';
$db = new Database();
$code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';
if (!$code || !$db->surveyExists($code)) header("Location: index.php");

if (!isset($_SESSION['participant_id'])) $_SESSION['participant_id'] = uniqid('p_', true);
$uid = $_SESSION['participant_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votación <?= $code ?></title>
    <style>
        body { background: #000; color: #fff; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; text-align: center; }
        .container { max-width: 600px; width: 100%; }
        h1 { margin-bottom: 30px; font-size: 1.8em; text-shadow: 0 0 10px #8a2be2; }
        .options-grid { display: flex; flex-direction: column; gap: 15px; }
        .btn-opt { 
            padding: 20px; font-size: 1.2em; border: none; border-radius: 12px; 
            background: linear-gradient(135deg, #333 0%, #444 100%); 
            color: #fff; cursor: pointer; transition: 0.2s; border: 1px solid #555;
        }
        .btn-opt:active { transform: scale(0.98); }
        .btn-opt.selected { background: linear-gradient(135deg, #8a2be2 0%, #4a0080 100%); border-color: #a040ff; }
        
        #timer-bar-container { 
            height: 10px; background: #333; border-radius: 5px; margin-bottom: 20px; 
            overflow: hidden; display: none; 
        }
        #timer-bar { height: 100%; background: #00d2ff; width: 100%; transition: width 1s linear; }
        
        .waiting { color: #666; animation: pulse 1.5s infinite; margin-top: 50px;}
        @keyframes pulse { 0%{opacity:0.5} 50%{opacity:1} 100%{opacity:0.5} }
    </style>
</head>
<body>
    <div class="container">
        <div id="active-area" style="display:none">
            <div id="timer-bar-container"><div id="timer-bar"></div></div>
            <h1 id="q-text"></h1>
            <div id="options-area" class="options-grid"></div>
        </div>
        <div id="waiting-area" class="waiting">
            <h2>Esperando pregunta...</h2>
            <div style="font-size:3em">⏳</div>
        </div>
    </div>

    <script>
        const CODE = '<?= $code ?>';
        const UID = '<?= $uid ?>';
        let currentQId = null;
        let hasVoted = false;
        let timerInterval = null;

        function check() {
            fetch(`api.php?action=check&code=${CODE}`)
                .then(r => r.json())
                .then(data => {
                    if (data.active) {
                        if (data.question.id !== currentQId) {
                            // Nueva pregunta
                            setupQuestion(data.question);
                        } else {
                            // Actualizar tiempo si es la misma
                            updateTimer(data.question);
                        }
                    } else {
                        resetView();
                    }
                });
        }

        function setupQuestion(q) {
            currentQId = q.id;
            hasVoted = false;
            
            document.getElementById('waiting-area').style.display = 'none';
            document.getElementById('active-area').style.display = 'block';
            document.getElementById('q-text').innerText = q.text;
            
            const optsDiv = document.getElementById('options-area');
            optsDiv.innerHTML = '';
            
            q.options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className = 'btn-opt';
                btn.innerText = opt.text;
                btn.onclick = () => sendVote(opt.option_index, btn);
                optsDiv.appendChild(btn);
            });

            updateTimer(q);
        }

        function updateTimer(q) {
            const barContainer = document.getElementById('timer-bar-container');
            const bar = document.getElementById('timer-bar');
            
            if (q.timer_total > 0 && q.timer_remaining !== null) {
                barContainer.style.display = 'block';
                const pct = (q.timer_remaining / q.timer_total) * 100;
                bar.style.width = pct + '%';
                
                // Color cambia si queda poco
                if (pct < 30) bar.style.background = '#ff0055';
                else bar.style.background = '#00d2ff';

                if (q.timer_remaining <= 0) {
                    disableButtons("Tiempo Agotado");
                }
            } else {
                barContainer.style.display = 'none';
            }
        }

        function sendVote(idx, btnElement) {
            if (hasVoted) return;
            
            // Efecto visual inmediato
            const allBtns = document.querySelectorAll('.btn-opt');
            allBtns.forEach(b => b.style.opacity = '0.5');
            btnElement.style.opacity = '1';
            btnElement.classList.add('selected');
            
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=answer&code=${CODE}&question_id=${currentQId}&participant_id=${UID}&answer_index=${idx}`
            }).then(r => r.json()).then(d => {
                if(d.success) {
                    hasVoted = true;
                    disableButtons("Voto Enviado");
                } else {
                    alert(d.message);
                }
            });
        }

        function disableButtons(msg) {
            const optsDiv = document.getElementById('options-area');
            // Mantener solo visualmente, pero desactivar clicks
            Array.from(optsDiv.children).forEach(btn => btn.disabled = true);
            if (!document.getElementById('msg-status')) {
                const div = document.createElement('div');
                div.id = 'msg-status';
                div.style.marginTop = '20px';
                div.style.color = '#00ffaa';
                div.innerText = msg;
                document.getElementById('active-area').appendChild(div);
            }
        }

        function resetView() {
            currentQId = null;
            document.getElementById('active-area').style.display = 'none';
            document.getElementById('waiting-area').style.display = 'block';
            if(document.getElementById('msg-status')) document.getElementById('msg-status').remove();
        }

        setInterval(check, 1000);
        check();
    </script>
</body>
</html>
