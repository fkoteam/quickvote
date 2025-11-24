<?php
// screen.php - Pantalla de resultados
$code = isset($_GET['code']) ? strtoupper($_GET['code']) : '';
if (!$code) die("Falta el código (?code=XYZ)");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pantalla <?= $code ?></title>
    <style>
        body { 
            background: #000; overflow: hidden; font-family: 'Segoe UI', sans-serif; 
            display: flex; flex-direction: column; height: 100vh; margin: 0; 
        }
        #star-bg { position: fixed; top:0; left:0; width:100%; height:100%; z-index:-1; }
        
        header { 
            padding: 20px; text-align: center; color: rgba(255,255,255,0.5); 
            font-size: 1.2em; letter-spacing: 5px; border-bottom: 1px solid rgba(255,255,255,0.1); 
        }

        #content { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 40px; }
        
        h1 { 
            color: #fff; font-size: 3em; text-align: center; margin: 0 0 50px 0; 
            text-shadow: 0 0 20px #8a2be2; line-height: 1.2;
        }

        .chart-container { 
            display: flex; align-items: flex-end; justify-content: center; gap: 40px; 
            height: 50vh; width: 80%; margin: 0 auto; 
        }

        .bar-group { 
            flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end;
        }

        .bar { 
            width: 100%; background: linear-gradient(to top, #8a2be2, #00d2ff); 
            border-radius: 10px 10px 0 0; transition: height 0.5s ease-out; 
            position: relative; min-height: 5px; box-shadow: 0 0 20px rgba(138, 43, 226, 0.4);
        }

        .bar-val { 
            position: absolute; top: -35px; width: 100%; text-align: center; 
            font-size: 2em; font-weight: bold; color: #fff; 
        }

        .bar-label { 
            margin-top: 15px; font-size: 1.5em; color: #ddd; text-align: center; 
            background: rgba(0,0,0,0.5); padding: 5px 15px; border-radius: 20px;
        }
        
        .timer-overlay {
            position: fixed; top: 20px; right: 20px; font-size: 2em; color: #fff;
            background: rgba(0,0,0,0.7); padding: 10px 20px; border-radius: 10px;
            border: 1px solid #333; display: none;
        }

        .idle-msg { text-align: center; color: #555; font-size: 2em; }
    </style>
</head>
<body>
    <canvas id="star-bg"></canvas>
    <header>EVENTO: <b><?= $code ?></b></header>
    <div id="timer-box" class="timer-overlay"></div>

    <div id="content">
        <div id="idle" class="idle-msg">Esperando votación...</div>
        <div id="active" style="display:none; height: 100%;">
            <h1 id="q-title"></h1>
            <div id="chart" class="chart-container"></div>
        </div>
    </div>

    <script>
        // Animación Fondo
        const cvs = document.getElementById('star-bg'), ctx = cvs.getContext('2d');
        let stars = [];
        const resize = () => { cvs.width = window.innerWidth; cvs.height = window.innerHeight; };
        window.onresize = resize; resize();
        for(let i=0; i<200; i++) stars.push({x:Math.random()*cvs.width, y:Math.random()*cvs.height, s:Math.random()*2});
        function draw(){
            ctx.clearRect(0,0,cvs.width,cvs.height);
            ctx.fillStyle='white';
            stars.forEach(st=>{
                ctx.globalAlpha = Math.random();
                ctx.beginPath(); ctx.arc(st.x, st.y, st.s, 0, 6.28); ctx.fill();
                st.y -= 0.2; if(st.y<0) st.y=cvs.height;
            });
            requestAnimationFrame(draw);
        }
        draw();

        // Lógica
        const CODE = '<?= $code ?>';
        let currentQId = 0;

        function refresh() {
            // 1. Ver qué pregunta está activa o pedir resultados
            fetch(`api.php?action=check&code=${CODE}`).then(r=>r.json()).then(status => {
                if(status.active) {
                    showQuestion(status.question);
                    currentQId = status.question.id;
                    fetchResults(); // Traer datos
                } else {
                    // Si no hay activa, pero tenemos una QId previa, seguimos mostrando los resultados finales
                    if(currentQId !== 0) {
                        fetchResults();
                    } else {
                        document.getElementById('idle').style.display = 'block';
                        document.getElementById('active').style.display = 'none';
                    }
                }
            });
        }

        function showQuestion(q) {
            document.getElementById('idle').style.display = 'none';
            document.getElementById('active').style.display = 'block';
            document.getElementById('q-title').innerText = q.text;
            
            // Timer
            const tBox = document.getElementById('timer-box');
            if(q.timer_total > 0 && q.timer_remaining !== null) {
                tBox.style.display = 'block';
                tBox.innerText = Math.ceil(q.timer_remaining) + "s";
                if(q.timer_remaining <= 5) tBox.style.color = '#ff3333';
                else tBox.style.color = '#fff';
            } else {
                tBox.style.display = 'none';
            }
        }

        function fetchResults() {
            if(!currentQId) return;
            fetch(`api.php?action=results&code=${CODE}&question_id=${currentQId}`)
                .then(r=>r.json())
                .then(d => renderChart(d.results, d.total));
        }

        function renderChart(results, total) {
            const container = document.getElementById('chart');
            // Si cambia la estructura de opciones, limpiar
            if(container.children.length !== Object.keys(results).length) {
                container.innerHTML = '';
                Object.values(results).forEach(r => {
                    const g = document.createElement('div'); g.className = 'bar-group';
                    g.innerHTML = `
                        <div class="bar" style="height:0%"><div class="bar-val">0</div></div>
                        <div class="bar-label">${r.text}</div>
                    `;
                    container.appendChild(g);
                });
            }

            // Actualizar alturas
            const groups = container.children;
            let i = 0;
            // Calcular máximo para escalar (si total es 0, evitar division por 0)
            // Para visualización, usamos % sobre total, o sobre el máximo votado? 
            // Mejor sobre total para ver participación, o sobre max para llenar pantalla.
            // Usaremos sobre Max Count para que siempre se vea alguna barra alta.
            let maxCount = 0;
            Object.values(results).forEach(r => maxCount = Math.max(maxCount, r.count));
            if(maxCount === 0) maxCount = 1;

            Object.values(results).forEach(r => {
                const height = (r.count / maxCount) * 80; // max 80% height
                const bar = groups[i].querySelector('.bar');
                const val = groups[i].querySelector('.bar-val');
                
                bar.style.height = height + '%';
                val.innerText = r.count; // Mostrar número absoluto
                i++;
            });
        }

        setInterval(refresh, 1000);
        refresh();
    </script>
</body>
</html>
