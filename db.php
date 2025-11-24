<?php
// db.php - Configuración y funciones de base de datos (actualizado)

class Database {
    private $db;
    
    public function __construct() {
        if (!file_exists('survey_data.db')) {
            die("Error: Base de datos no encontrada. Ejecuta install.php primero.");
        }
        $this->db = new SQLite3('survey_data.db');
        $this->db->busyTimeout(5000);
    }
    

    // CRUD de preguntas
    public function createQuestion($text, $numOptions = 2, $optionLabels = '["SÍ","NO"]', $autoClose = 0, $closeSeconds = 30) {
        $stmt = $this->db->prepare("
            INSERT INTO questions (text, num_options, option_labels, auto_close, close_seconds) 
            VALUES (:text, :num_options, :option_labels, :auto_close, :close_seconds)
        ");
        $stmt->bindValue(':text', $text, SQLITE3_TEXT);
        $stmt->bindValue(':num_options', $numOptions, SQLITE3_INTEGER);
        $stmt->bindValue(':option_labels', $optionLabels, SQLITE3_TEXT);
        $stmt->bindValue(':auto_close', $autoClose, SQLITE3_INTEGER);
        $stmt->bindValue(':close_seconds', $closeSeconds, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function getQuestions() {
        $result = $this->db->query("SELECT * FROM questions ORDER BY id");
        $questions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $questions[] = $row;
        }
        return $questions;
    }
    
    public function getQuestion($id) {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function updateQuestion($id, $text, $numOptions, $optionLabels, $autoClose, $closeSeconds) {
        $stmt = $this->db->prepare("
            UPDATE questions 
            SET text = :text, 
                num_options = :num_options, 
                option_labels = :option_labels,
                auto_close = :auto_close,
                close_seconds = :close_seconds
            WHERE id = :id
        ");
        $stmt->bindValue(':text', $text, SQLITE3_TEXT);
        $stmt->bindValue(':num_options', $numOptions, SQLITE3_INTEGER);
        $stmt->bindValue(':option_labels', $optionLabels, SQLITE3_TEXT);
        $stmt->bindValue(':auto_close', $autoClose, SQLITE3_INTEGER);
        $stmt->bindValue(':close_seconds', $closeSeconds, SQLITE3_INTEGER);
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
        $startedAt = $status === 'on' ? date('Y-m-d H:i:s') : null;
        
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO question_status (survey_code, question_id, status, started_at, updated_at) 
            VALUES (:survey_code, :question_id, :status, :started_at, CURRENT_TIMESTAMP)
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':started_at', $startedAt, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function getQuestionStatus($surveyCode, $questionId) {
        $stmt = $this->db->prepare("
            SELECT status, started_at FROM question_status 
            WHERE survey_code = :survey_code AND question_id = :question_id
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? $row : ['status' => 'off', 'started_at' => null];
    }
    
    public function getCurrentQuestion($surveyCode) {
        $stmt = $this->db->prepare("
            SELECT question_id, started_at FROM question_status 
            WHERE survey_code = :survey_code AND status = 'on'
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? $row : null;
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
        $stmt->bindValue(':answer', $answer, SQLITE3_INTEGER);
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
        
        $results = [];
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
    
    public function resetAnswers($surveyCode, $questionId) {
        $stmt = $this->db->prepare("
            DELETE FROM answers 
            WHERE survey_code = :survey_code AND question_id = :question_id
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function resetAllAnswers($surveyCode) {
        $stmt = $this->db->prepare("DELETE FROM answers WHERE survey_code = :survey_code");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        return $stmt->execute();
    }
}
?>
