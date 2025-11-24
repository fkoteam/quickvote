<?php
// install.php - Instalación y configuración inicial del sistema
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Crear base de datos
        $db = new SQLite3('survey_data.db');
        
        // Tabla de preguntas con opciones múltiples
        $db->exec("
            CREATE TABLE IF NOT EXISTS questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                text TEXT NOT NULL,
                num_options INTEGER DEFAULT 2,
                option_labels TEXT NOT NULL,
                auto_close INTEGER DEFAULT 0,
                close_seconds INTEGER DEFAULT 30,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Tabla de instancias de encuesta
        $db->exec("
            CREATE TABLE IF NOT EXISTS survey_instances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Tabla de respuestas
        $db->exec("
            CREATE TABLE IF NOT EXISTS answers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                survey_code TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                session_id TEXT NOT NULL,
                answer INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(survey_code, question_id, session_id)
            )
        ");
        
        // Tabla de estado de preguntas
        $db->exec("
            CREATE TABLE IF NOT EXISTS question_status (
                survey_code TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                status TEXT DEFAULT 'off',
                started_at DATETIME,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (survey_code, question_id)
            )
        ");
        
        $db->close();
        
        $success = true;
        $message = "¡Base de datos instalada correctamente! Puedes acceder al panel de administración.";
    } catch (Exception $e) {
        $success = false;
        $message = "Error durante la instalación: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Sistema de Encuestas</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .container {
            background: rgba(20, 20, 30, 0.9);
            padding: 50px 40px;
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            border: 1px solid rgba(138, 43, 226, 0.3);
            box-shadow: 0 0 60px rgba(138, 43, 226, 0.3);
        }

        h1 {
            color: #fff;
            font-size: 2.5em;
            margin-bottom: 15px;
            text-shadow: 0 0 30px rgba(138, 43, 226, 0.5);
            text-align: center;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.6);
            text-align: center;
            margin-bottom: 40px;
            font-size: 1.1em;
        }

        .info-box {
            background: rgba(0, 0, 0, 0.3);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 1px solid rgba(138, 43, 226, 0.2);
        }

        .info-box h3 {
            color: #8a2be2;
            margin-bottom: 15px;
        }

        .info-box ul {
            list-style: none;
            padding-left: 0;
        }

        .info-box li {
            padding: 8px 0;
            color: rgba(255, 255, 255, 0.8);
        }

        .info-box li:before {
            content: "✓ ";
            color: #96c93d;
            font-weight: bold;
            margin-right: 10px;
        }

        .btn {
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

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(138, 43, 226, 0.5);
        }

        .message {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }

        .message.success {
            background: linear-gradient(135deg, rgba(0, 176, 155, 0.2) 0%, rgba(150, 201, 61, 0.2) 100%);
            border: 1px solid rgba(0, 176, 155, 0.5);
            color: #96c93d;
        }

        .message.error {
            background: linear-gradient(135deg, rgba(235, 51, 73, 0.2) 0%, rgba(244, 92, 67, 0.2) 100%);
            border: 1px solid rgba(235, 51, 73, 0.5);
            color: #f45c43;
        }

        .links {
            margin-top: 30px;
            text-align: center;
        }

        .links a {
            color: #8a2be2;
            text-decoration: none;
            font-weight: 600;
            margin: 0 15px;
            transition: all 0.3s ease;
        }

        .links a:hover {
            color: #fff;
            text-shadow: 0 0 10px rgba(138, 43, 226, 0.5);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✦ Instalación del Sistema</h1>
        <p class="subtitle">Configura tu sistema de encuestas interactivas</p>

        <?php if (isset($success)): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
            <?php if ($success): ?>
                <div class="links">
                    <a href="admin.php">→ Ir al Panel de Administración</a>
                    <a href="index.php">→ Ir a la Página Principal</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="info-box">
                <h3>Características del Sistema:</h3>
                <ul>
                    <li>Preguntas con número configurable de opciones (2-10)</li>
                    <li>Cierre automático o manual de preguntas</li>
                    <li>Temporizador visual para participantes</li>
                    <li>Visualización de resultados en tiempo real</li>
                    <li>Panel de administración completo</li>
                    <li>API REST para integración externa</li>
                </ul>
            </div>

            <form method="POST">
                <button type="submit" class="btn">Instalar Base de Datos</button>
            </form>

            <div class="info-box" style="margin-top: 30px;">
                <h3>Requisitos:</h3>
                <ul>
                    <li>PHP 7.0 o superior</li>
                    <li>SQLite3 habilitado</li>
                    <li>Permisos de escritura en el directorio</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
