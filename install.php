<?php
// install.php - Inicializador de Base de Datos
if (file_exists('survey_data.db')) {
    echo "<h1>La base de datos ya existe.</h1><p>Bórrala manualmente si quieres reinstalar.</p>";
    exit;
}

$db = new SQLite3('survey_data.db');

// Tabla Preguntas
$db->exec("CREATE TABLE IF NOT EXISTS questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    text TEXT NOT NULL,
    timer_seconds INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Tabla Opciones de Respuesta
$db->exec("CREATE TABLE IF NOT EXISTS question_options (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    question_id INTEGER NOT NULL,
    option_index INTEGER NOT NULL,
    text TEXT NOT NULL,
    FOREIGN KEY(question_id) REFERENCES questions(id)
)");

// Tabla Instancias (Eventos)
$db->exec("CREATE TABLE IF NOT EXISTS survey_instances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Tabla Respuestas
$db->exec("CREATE TABLE IF NOT EXISTS answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    survey_code TEXT NOT NULL,
    question_id INTEGER NOT NULL,
    session_id TEXT NOT NULL,
    answer_index INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(survey_code, question_id, session_id)
)");

// Tabla Estado (Control de flujo)
$db->exec("CREATE TABLE IF NOT EXISTS question_status (
    survey_code TEXT NOT NULL,
    question_id INTEGER NOT NULL,
    status TEXT DEFAULT 'off',
    start_time INTEGER DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (survey_code, question_id)
)");

echo "<h1>Instalación Completada</h1><p>Base de datos creada exitosamente. <a href='admin.php'>Ir al Admin</a></p>";
?>
