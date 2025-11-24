<?php
// screen.php - Pantalla visual de resultados
$code = isset($_GET['code']) ? strtoupper($_GET['code']) : '';
if (!$code) die("Falta el código. Usa: screen.php?code=TU_CODIGO");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultados: <?= htmlspecialchars($code) ?></title>
    <style>
        body { margin: 0; background: #000; color: #fff; font-family: 'Segoe UI', sans-serif; overflow: hidden; display: flex; flex-direction: column; height: 100vh; }
        #stars-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        
        header { padding: 20px; text-align: center; font-size: 1.5em; letter-spacing: 5px; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.1); }
        
        #main-container { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; }
        
        h1 { font-size: 3.5em; text-align: center; margin-bottom: 50px; text-shadow: 0 0 30px #8a2be2; width: 100%; }
        
        #idle-msg { font-size: 2em; color: rgba(255,255,255,0.5); animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 0.5; } 50% { opacity: 1; } 100% { opacity: 0.5; } }

        /* GRÁFICO DE BARRAS */
        .chart { display: flex; align-items: flex-end; justify-content: center; height: 50vh; width: 90%; gap: 30px; }
        .bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; }
        .bar { width: 100%; background: linear-gradient(to top, #4a0080, #8a2be2); border-radius: 10px 10px 0 0; transition: height 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; min-height: 5px; box-shadow: 0 0 30px rgba(138, 43, 226, 0.4); border: 1px solid rgba(255,255,255,0.2); }
        .bar-value { position: absolute; top: -40px; width: 100%; text-align: center; font-size: 2.5em; font-weight: bold; text-shadow: 0 0 10px rgba(0,0,0,0.8); }
        .bar-label { margin-top: 20px; font-size: 1.5em; text-align: center; background: rgba(255,255,255,0.1); padding: 10px 20px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); width: 100%; }

        /* TIMER FLOTANTE */
        .timer-float { position: fixed; top: 20px; right: 20px; font-size: 2.5em; font-weight: bold; padding: 10px 30px; border-radius: 15px; background: rgba(0,0,0,0.6); border: 2px solid #8a2be2; display: none; }
        .timer-float.urgent { color: #ff4444; border-color: #ff4444; animation: shake 0.5s infinite; }
        @keyframes shake { 0% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } 100% { transform: translateX(0); } }
    </style>
</head>
<body>
    <canvas id="stars-canvas"></canvas>
    <header>EVENTO: <strong><?= $code ?></strong></header>
    <div id="timerDisplay" class="timer-float"></div>

    <div id="main-container">
        <div id="idle-msg">Esperando votación...</div>
        
        <div id="active-content" style="display:none; width: 100%;">
            <h1 id="questionTitle"></h1>
            <div id="chartContainer" class="chart"></div>
        </div>
    </div>

    <script>
        const CODE = '<?= $code ?>';
        let currentQId = null;

        function refresh() {
            fetch(`api.php?action=check&code=${CODE}`).then(r=>r.json()).then(status => {
                const idleDiv = document.getElementById('idle-msg');
                const activeDiv = document.getElementById('active-content');

                if (status.question_id) {
                    // Pregunta Activa
                    currentQId = status.question_id;
                    idleDiv.style.display = 'none';
                    activeDiv.style.display = 'block';
                    document.getElementById('questionTitle').innerText = status.question_text;
                    
                    // Timer
                    updateTimer(status.timer_total, status.timer_remaining);
                    
                    // Cargar Resultados
                    loadResults(currentQId);
                } else {
                    // Si no hay activa pero teníamos una cargada, seguimos mostrando resultados (sin timer)
                    document.getElementById('timerDisplay').style.display = 'none';
                    if (currentQId) {
                        loadResults(currentQId);
                    } else {
                        idleDiv.style.display = 'block';
                        activeDiv.style.display = 'none';
                    }
                }
            });
        }

        function updateTimer(total, remaining) {
            const tDiv = document.getElementById('timerDisplay');
            if (total > 0 && remaining !== null) {
                tDiv.style.display = 'block';
                tDiv.innerText = Math.ceil(remaining);
                if (remaining <= 5) tDiv.classList.add('urgent');
                else tDiv.classList.remove('urgent');
            } else {
                tDiv.style.display = 'none';
            }
        }

        function loadResults(qId) {
            fetch(`api.php?action=results&code=${CODE}&question_id=${qId}`)
            .then(r=>r.json())
            .then(data => drawChart(data.results));
        }

        function drawChart(results) {
            const container = document.getElementById('chartContainer');
            
            // Si cambia la estructura de respuestas, reconstruir DOM
            if (container.children.length !== Object.keys(results).length) {
                container.innerHTML = '';
                Object.values(results).forEach(res => {
                    const group = document.createElement('div');
                    group.className = 'bar-group';
                    group.innerHTML = `
                        <div class="bar" style="height: 0%"><div class="bar-value">0</div></div>
                        <div class="bar-label">${res.text}</div>
                    `;
                    container.appendChild(group);
                });
            }

            // Actualizar alturas
            const groups = container.children;
            let maxVal = 0;
            Object.values(results).forEach(r => { if(r.count > maxVal) maxVal = r.count; });
            if (maxVal === 0) maxVal = 1; // Evitar div/0

            let i = 0;
            Object.values(results).forEach(res => {
                const bar = groups[i].querySelector('.bar');
                const val = groups[i].querySelector('.bar-value');
                
                // Altura relativa al máximo valor (para que siempre haya una barra alta)
                const heightPct = (res.count / maxVal) * 80; 
                bar.style.height = Math.max(heightPct, 1) + '%'; // Mínimo 1% para que se vea la base
                val.innerText = res.count;
                i++;
            });
        }

        setInterval(refresh, 1000);
        
        // ESTRELLAS (Mismo script)
        const cvs = document.getElementById('stars-canvas'), ctx = cvs.getContext('2d');
        let stars = [];
        const resize = () => { cvs.width = window.innerWidth; cvs.height = window.innerHeight; };
        window.onresize = resize; resize();
        for(let i=0;i<200;i++) stars.push({x:Math.random()*cvs.width,y:Math.random()*cvs.height,s:Math.random()*2});
        function draw(){ ctx.clearRect(0,0,cvs.width,cvs.height); stars.forEach(st=>{ ctx.globalAlpha=Math.random(); ctx.beginPath(); ctx.arc(st.x,st.y,st.s,0,6.28); ctx.fillStyle='white'; ctx.fill(); st.y-=0.2; if(st.y<0)st.y=cvs.height; }); requestAnimationFrame(draw); }
        draw();
    </script>
</body>
</html>
