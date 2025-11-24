<?php
// install.php - Inicializador de Base de Datos
if (file_exists('survey_data.db')) {
    echo "<h1>La base de datos ya existe.</h1><p>Por seguridad, borra el archivo 'survey_data.db' manualmente si quieres reinstalar desde cero.</p>";
    exit;
}

try {
    $db = new SQLite3('survey_data.db');
    
    // Tabla Preguntas (Añadido timer_seconds)
    $db->exec("CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        text TEXT NOT NULL,
        timer_seconds INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabla Opciones (Nueva: para soportar N respuestas)
    $db->exec("CREATE TABLE IF NOT EXISTS question_options (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question_id INTEGER NOT NULL,
        option_index INTEGER NOT NULL,
        text TEXT NOT NULL,
        FOREIGN KEY(question_id) REFERENCES questions(id)
    )");

    // Tabla Instancias
    $db->exec("CREATE TABLE IF NOT EXISTS survey_instances (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabla Respuestas (Modificada: guarda answer_index en lugar de texto 'yes/no')
    $db->exec("CREATE TABLE IF NOT EXISTS answers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_code TEXT NOT NULL,
        question_id INTEGER NOT NULL,
        session_id TEXT NOT NULL,
        answer_index INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(survey_code, question_id, session_id)
    )");

    // Tabla Estado (Añadido start_time para el temporizador)
    $db->exec("CREATE TABLE IF NOT EXISTS question_status (
        survey_code TEXT NOT NULL,
        question_id INTEGER NOT NULL,
        status TEXT DEFAULT 'off',
        start_time INTEGER DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (survey_code, question_id)
    )");

    echo "<h1>Instalación Completada</h1><p>Estructura creada correctamente. <a href='admin.php'>Ir al Panel de Administración</a></p>";

} catch (Exception $e) {
    echo "Error fatal: " . $e->getMessage();
}
?>
