<?php
// index.php - Página principal donde los participantes ingresan el código
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tus respuestas marcarán el camino</title>
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
            background: rgba(20, 20, 30, 0.9);
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 0 60px rgba(138, 43, 226, 0.3), 
                        0 0 100px rgba(138, 43, 226, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: fadeIn 0.8s ease;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(138, 43, 226, 0.3);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h1 {
            color: #fff;
            font-size: 2.5em;
            margin-bottom: 15px;
            font-weight: 700;
            text-shadow: 0 0 30px rgba(138, 43, 226, 0.5);
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.6);
            font-size: 1.1em;
            margin-bottom: 40px;
        }

        .input-group {
            margin-bottom: 30px;
        }

        input[type="text"] {
            width: 100%;
            padding: 18px 25px;
            font-size: 1.3em;
            border: 2px solid rgba(138, 43, 226, 0.5);
            border-radius: 12px;
            text-align: center;
            letter-spacing: 3px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            font-weight: 600;
            background: rgba(0, 0, 0, 0.5);
            color: #fff;
        }

        input[type="text"]::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #8a2be2;
            box-shadow: 0 0 20px rgba(138, 43, 226, 0.4);
        }

        button {
            width: 100%;
            padding: 18px;
            font-size: 1.2em;
            background: linear-gradient(135deg, #8a2be2 0%, #4a0080 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(138, 43, 226, 0.5);
        }

        button:active {
            transform: translateY(0);
        }

        .admin-link {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-link a {
            color: rgba(138, 43, 226, 0.8);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .admin-link a:hover {
            color: #8a2be2;
            text-shadow: 0 0 10px rgba(138, 43, 226, 0.5);
        }

        @media (max-width: 600px) {
            .container {
                padding: 40px 25px;
            }

            h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <canvas id="stars-canvas"></canvas>
    
    <div class="container">
        <h1>Tus respuestas marcarán el camino</h1>
        <p class="subtitle">Ingresa el código proporcionado para comenzar</p>
        
        <form action="survey.php" method="GET">
            <div class="input-group">
                <input type="text" 
                       name="code" 
                       placeholder="Código" 
                       required 
                       autocomplete="off"
                       pattern="[A-Za-z0-9]+"
                       maxlength="10">
            </div>
            <button type="submit">Ingresar</button>
        </form>

        <div class="admin-link">
            <a href="admin.php">Panel de Administración</a>
        </div>
    </div>

    <script>
        // Animación de estrellas
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
                // Efecto de parpadeo suave
                star.opacity += star.twinkle;
                if (star.opacity > 0.7 || star.opacity < 0.2) {
                    star.twinkle = -star.twinkle;
                }

                // Movimiento lento hacia arriba
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
