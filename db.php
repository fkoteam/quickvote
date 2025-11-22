<?php
// db.php - Configuración y funciones de base de datos

class Database {
    private $db;
    
    public function __construct() {
        $this->db = new SQLite3('survey_data.db');
        $this->createTables();
    }
    
    private function createTables() {
        // Tabla de preguntas con textos personalizables para respuestas
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                text TEXT NOT NULL,
                yes_text TEXT DEFAULT 'SÍ',
                no_text TEXT DEFAULT 'NO',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Agregar columnas si no existen (para migración)
        @$this->db->exec("ALTER TABLE questions ADD COLUMN yes_text TEXT DEFAULT 'SÍ'");
        @$this->db->exec("ALTER TABLE questions ADD COLUMN no_text TEXT DEFAULT 'NO'");
        
        // Tabla de instancias de encuesta
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS survey_instances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Tabla de respuestas
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS answers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                survey_code TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                session_id TEXT NOT NULL,
                answer TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(survey_code, question_id, session_id)
            )
        ");
        
        // Tabla de estado de preguntas
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS question_status (
                survey_code TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                status TEXT DEFAULT 'off',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (survey_code, question_id)
            )
        ");
    }
    
    // CRUD de preguntas
    public function createQuestion($text, $yesText = 'SÍ', $noText = 'NO') {
        $stmt = $this->db->prepare("INSERT INTO questions (text, yes_text, no_text) VALUES (:text, :yes_text, :no_text)");
        $stmt->bindValue(':text', $text, SQLITE3_TEXT);
        $stmt->bindValue(':yes_text', $yesText, SQLITE3_TEXT);
        $stmt->bindValue(':no_text', $noText, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function getQuestions() {
        $result = $this->db->query("SELECT * FROM questions ORDER BY id");
        $questions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Valores por defecto si no existen
            $row['yes_text'] = $row['yes_text'] ?? 'SÍ';
            $row['no_text'] = $row['no_text'] ?? 'NO';
            $questions[] = $row;
        }
        return $questions;
    }
    
    public function getQuestion($id) {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $row['yes_text'] = $row['yes_text'] ?? 'SÍ';
            $row['no_text'] = $row['no_text'] ?? 'NO';
        }
        return $row;
    }
    
    public function updateQuestion($id, $text, $yesText = 'SÍ', $noText = 'NO') {
        $stmt = $this->db->prepare("UPDATE questions SET text = :text, yes_text = :yes_text, no_text = :no_text WHERE id = :id");
        $stmt->bindValue(':text', $text, SQLITE3_TEXT);
        $stmt->bindValue(':yes_text', $yesText, SQLITE3_TEXT);
        $stmt->bindValue(':no_text', $noText, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function deleteQuestion($id) {
        $stmt = $this->db->prepare("DELETE FROM questions WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    // Instancias de encuesta
    public function createSurveyInstance($code) {
        $stmt = $this->db->prepare("INSERT INTO survey_instances (code) VALUES (:code)");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function getSurveyInstances() {
        $result = $this->db->query("SELECT * FROM survey_instances ORDER BY created_at DESC");
        $instances = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $instances[] = $row;
        }
        return $instances;
    }
    
    public function surveyExists($code) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM survey_instances WHERE code = :code");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row['count'] > 0;
    }
    
    public function deleteSurveyInstance($code) {
        // Eliminar también las respuestas y estados asociados
        $stmt = $this->db->prepare("DELETE FROM answers WHERE survey_code = :code");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $stmt->execute();
        
        $stmt = $this->db->prepare("DELETE FROM question_status WHERE survey_code = :code");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $stmt->execute();
        
        $stmt = $this->db->prepare("DELETE FROM survey_instances WHERE code = :code");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    // Estado de preguntas
    public function setQuestionStatus($surveyCode, $questionId, $status) {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO question_status (survey_code, question_id, status, updated_at) 
            VALUES (:survey_code, :question_id, :status, CURRENT_TIMESTAMP)
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function getQuestionStatus($surveyCode, $questionId) {
        $stmt = $this->db->prepare("
            SELECT status FROM question_status 
            WHERE survey_code = :survey_code AND question_id = :question_id
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? $row['status'] : 'off';
    }
    
    public function getCurrentQuestion($surveyCode) {
        $stmt = $this->db->prepare("
            SELECT question_id FROM question_status 
            WHERE survey_code = :survey_code AND status = 'on'
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? $row['question_id'] : null;
    }
    
    // Respuestas
    public function saveAnswer($surveyCode, $questionId, $sessionId, $answer) {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO answers (survey_code, question_id, session_id, answer, created_at) 
            VALUES (:survey_code, :question_id, :session_id, :answer, CURRENT_TIMESTAMP)
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
        $stmt->bindValue(':answer', $answer, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function getResults($surveyCode, $questionId) {
        $stmt = $this->db->prepare("
            SELECT answer, COUNT(*) as count 
            FROM answers 
            WHERE survey_code = :survey_code AND question_id = :question_id 
            GROUP BY answer
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $results = ['yes' => 0, 'no' => 0];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $results[$row['answer']] = $row['count'];
        }
        
        return $results;
    }
    
    public function hasAnswered($surveyCode, $questionId, $sessionId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM answers 
            WHERE survey_code = :survey_code AND question_id = :question_id AND session_id = :session_id
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row['count'] > 0;
    }
    
    // Reiniciar respuestas de una pregunta en una instancia
    public function resetAnswers($surveyCode, $questionId) {
        $stmt = $this->db->prepare("
            DELETE FROM answers 
            WHERE survey_code = :survey_code AND question_id = :question_id
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    // Reiniciar TODAS las respuestas de una instancia
    public function resetAllAnswers($surveyCode) {
        $stmt = $this->db->prepare("DELETE FROM answers WHERE survey_code = :survey_code");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        return $stmt->execute();
    }
}
?>