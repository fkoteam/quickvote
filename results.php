<?php
// results.php - Visualización elegante de resultados
require_once 'db.php';

$db = new Database();
$code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';
$questionId = isset($_GET['question_id']) ? intval($_GET['question_id']) : 0;

if (!$code || !$questionId) {
    header('Location: admin.php');
    exit;
}

$question = $db->getQuestion($questionId);
$results = $db->getResults($code, $questionId);

if (!$question) {
    die('Pregunta no encontrada');
}

$optionLabels = json_decode($question['option_labels'], true);
$total = array_sum($results);

// Calcular porcentajes y encontrar ganador
$percentages = [];
$maxVotes = 0;
$winner = null;

for ($i = 1; $i <= $question['num_options']; $i++) {
    $votes = $results[$i] ?? 0;
    $percentages[$i] = $total > 0 ? ($votes / $total) * 100 : 0;
    
    if ($votes > $maxVotes) {
        $maxVotes = $votes;
        $winner = $i;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados - <?php echo htmlspecialchars($question['text']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0f;
            min-height: 100vh;
            padding: 20px;
            color: #fff;
            overflow-x: hidden;
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
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .header {
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.3) 0%, rgba(74, 0, 128, 0.3) 100%);
            border: 1px solid rgba(138, 43, 226, 0.5);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            text-align: center;
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .code-badge {
            display: inline-block;
            background: linear-gradient(135deg, #8a2be2 0%, #4a0080 100%);
            padding: 10px 25px;
            border-radius: 50px;
            font-size: 1em;
            letter-spacing: 2px;
            margin-bottom: 15px;
            box-shadow: 0 5px 20px rgba(138, 43, 226, 0.5);
        }

        h1 {
            font-size: 2.5em;
            margin-bottom: 15px;
            text-shadow: 0 0 30px rgba(138, 43, 226, 0.5);
        }

        .question-text {
            font-size: 1.4em;
            color: rgba(255, 255, 255, 0.8);
            max-width: 800px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            animation: slideUp 0.6s ease 0.2s both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card {
            background: rgba(20, 20, 30, 0.9);
            padding: 30px;
            border-radius: 15px;
            border: 1px solid rgba(138, 43, 226, 0.3);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(138, 43, 226, 0.4);
        }

        .stat-value {
            font-size: 3em;
            font-weight: 700;
            background: linear-gradient(135deg, #8a2be2 0%, #4a0080 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 1.1em;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 10px;
        }

        .results-section {
            background: rgba(20, 20, 30, 0.9);
            padding: 40px;
            border-radius: 20px;
            border: 1px solid rgba(138, 43, 226, 0.3);
            margin-bottom: 30px;
            animation: slideUp 0.6s ease 0.4s both;
        }

        .result-item {
            margin-bottom: 30px;
            animation: fadeIn 0.6s ease both;
        }

        .result-item:nth-child(1) { animation-delay: 0.5s; }
        .result-item:nth-child(2) { animation-delay: 0.6s; }
        .result-item:nth-child(3) { animation-delay: 0.7s; }
        .result-item:nth-child(4) { animation-delay: 0.8s; }
        .result-item:nth-child(5) { animation-delay: 0.9s; }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .option-label {
            font-size: 1.3em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .winner-badge {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.7em;
            box-shadow: 0 0 20px rgba(150, 201, 61, 0.5);
            animation: pulse 2s ease infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .result-stats {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 1.2em;
        }

        .votes-count {
            color: #8a2be2;
            font-weight: 700;
        }

        .percentage {
            color: rgba(255, 255, 255, 0.7);
        }

        .progress-bar-container {
            width: 100%;
            height: 40px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 25px;
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(138, 43, 226, 0.3);
        }

        .progress-bar {
            height: 100%;
            border-radius: 25px;
            transition: width 1.5s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .progress-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .progress-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .progress-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .progress-4 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .progress-5 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .progress-6 { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
        .progress-7 { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .progress-8 { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); }
        .progress-9 { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); }
        .progress-10 { background: linear-gradient(135deg, #ff6e7f 0%, #bfe9ff 100%); }

        .chart-container {
            margin-top: 40px;
            padding: 30px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            border: 1px solid rgba(138, 43, 226, 0.2);
        }

        .chart-title {
            text-align: center;
            font-size: 1.5em;
            margin-bottom: 30px;
            color: #8a2be2;
        }

        .bar-chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 300px;
            gap: 20px;
        }

        .bar {
            flex: 1;
            background: linear-gradient(to top, #8a2be2, #4a0080);
            border-radius: 10px 10px 0 0;
            position: relative;
            transition: all 0.6s ease;
            min-width: 40px;
            animation: growBar 1.5s ease both;
        }

        @keyframes growBar {
            from { height: 0; opacity: 0; }
            to { opacity: 1; }
        }

        .bar:hover {
            filter: brightness(1.3);
            transform: translateY(-10px);
        }

        .bar-label {
            position: absolute;
            bottom: -35px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.9em;
            text-align: center;
            width: 120%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bar-value {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            font-weight: 700;
            font-size: 1.1em;
            color: #96c93d;
        }

        .back-button {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #8a2be2 0%, #4a0080 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(138, 43, 226, 0.5);
        }

        .refresh-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 1.5em;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0, 176, 155, 0.5);
            transition: all 0.3s ease;
            z-index: 100;
        }

        .refresh-button:hover {
            transform: rotate(180deg) scale(1.1);
        }

        @media (max-width: 768px) {
            .header { padding: 25px; }
            h1 { font-size: 1.8em; }
            .question-text { font-size: 1.1em; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .bar-chart { height: 200px; }
            .result-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <canvas id="stars-canvas"></canvas>
    
    <div class="container">
        <div class="header">
            <div class="code-badge"><?php echo htmlspecialchars($code); ?></div>
            <h1>📊 Resultados</h1>
            <p class="question-text"><?php echo htmlspecialchars($question['text']); ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total; ?></div>
                <div class="stat-label">Total de Votos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $question['num_options']; ?></div>
                <div class="stat-label">Opciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $winner ? htmlspecialchars($optionLabels[$winner - 1]) : '-'; ?></div>
                <div class="stat-label">Ganador</div>
            </div>
        </div>

        <div class="results-section">
            <h2 style="margin-bottom: 30px; color: #fff;">Desglose por Opción</h2>
            <?php for ($i = 1; $i <= $question['num_options']; $i++): 
                $votes = $results[$i] ?? 0;
                $percentage = $percentages[$i];
                $isWinner = ($i === $winner && $votes > 0);
            ?>
                <div class="result-item">
                    <div class="result-header">
                        <div class="option-label">
                            <span><?php echo htmlspecialchars($optionLabels[$i - 1]); ?></span>
                            <?php if ($isWinner): ?>
                                <span class="winner-badge">🏆 GANADOR</span>
                            <?php endif; ?>
                        </div>
                        <div class="result-stats">
                            <span class="votes-count"><?php echo $votes; ?> votos</span>
                            <span class="percentage"><?php echo number_format($percentage, 1); ?>%</span>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar progress-<?php echo $i; ?>" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <div class="chart-container">
            <h3 class="chart-title">Gráfico Comparativo</h3>
            <div class="bar-chart">
                <?php for ($i = 1; $i <= $question['num_options']; $i++): 
                    $votes = $results[$i] ?? 0;
                    $percentage = $percentages[$i];
                    $height = $total > 0 ? ($votes / $total) * 100 : 0;
                ?>
                    <div class="bar" style="height: <?php echo $height * 2.5; ?>px; animation-delay: <?php echo $i * 0.1; ?>s;">
                        <div class="bar-value"><?php echo $votes; ?></div>
                        <div class="bar-label"><?php echo htmlspecialchars($optionLabels[$i - 1]); ?></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div style="text-align: center; margin-top: 40px;">
            <a href="admin.php" class="back-button">← Volver al Panel</a>
        </div>
    </div>

    <button class="refresh-button" onclick="location.reload()" title="Actualizar resultados">🔄</button>

    <script>
        // Animación de estrellas (igual que en otras páginas)
        const canvas = document.getElementById('stars-canvas');
        const ctx = canvas.getContext('2d');
        let stars = [];
        const numStars = 200;

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
                    speed: Math.random() * 0.3 + 0.1,
                    twinkle: Math.random() * 0.02
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

        // Auto-refresh cada 10 segundos
        setInterval(() => location.reload(), 10000);
    </script>
</body>
</html>
