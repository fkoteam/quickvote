<?php
// install.php - Actualizado para MySQL
session_start();
require_once 'db.php'; // Cargamos para usar la conexión PDO si ya existe la DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database(); // Usará las credenciales de config.php

        // Tablas con sintaxis MySQL
        $db->exec("
            CREATE TABLE IF NOT EXISTS questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                text TEXT NOT NULL,
                num_options INT DEFAULT 2,
                option_labels TEXT NOT NULL,
                auto_close TINYINT(1) DEFAULT 0,
                close_seconds INT DEFAULT 30,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;
        ");
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS survey_instances (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) UNIQUE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;
        ");
        
        // Tabla answers actualizada con campo EMAIL y clave única compuesta
        $db->exec("
            CREATE TABLE IF NOT EXISTS answers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                survey_code VARCHAR(50) NOT NULL,
                question_id INT NOT NULL,
                session_id VARCHAR(100) NOT NULL,
                answer INT NOT NULL,
                email VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_answer (survey_code, question_id, session_id)
            ) ENGINE=InnoDB;
        ");
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS question_status (
                survey_code VARCHAR(50) NOT NULL,
                question_id INT NOT NULL,
                status VARCHAR(20) DEFAULT 'off',
                started_at DATETIME NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (survey_code, question_id)
            ) ENGINE=InnoDB;
        ");
        
        $success = true;
        $message = "¡Base de datos MySQL instalada y actualizada correctamente!";
    } catch (Exception $e) {
        $success = false;
        $message = "Error: " . $e->getMessage();
    }
}
?>
<!-- El resto del HTML de install.php se mantiene igual -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalación MySQL</title>
    <!-- Estilos iguales a tu original -->
    <style>body{background:#0a0a0f;color:white;font-family:sans-serif;padding:50px;text-align:center}</style>
</head>
<body>
    <h1>Instalación MySQL</h1>
    <?php if(isset($message)) echo "<p>$message</p>"; ?>
    <form method="POST"><button type="submit" style="padding:15px;">Instalar Tablas</button></form>
</body>
</html>
