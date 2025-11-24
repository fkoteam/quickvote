<?php
// db.php - Configuración y funciones de base de datos

class Database {
    private $db;
    
    public function __construct() {
        if (!file_exists('survey_data.db')) {
            die("Error: Base de datos no encontrada. Ejecuta <a href='install.php'>install.php</a> primero.");
        }
        $this->db = new SQLite3('survey_data.db');
        $this->db->busyTimeout(5000);
    }
    
    // --- GESTIÓN DE PREGUNTAS ---

    public function createQuestion($text, $options, $timerSeconds = 0) {
        $this->db->exec('BEGIN');
        try {
            $stmt = $this->db->prepare("INSERT INTO questions (text, timer_seconds) VALUES (:text, :timer)");
            $stmt->bindValue(':text', $text, SQLITE3_TEXT);
            $stmt->bindValue(':timer', $timerSeconds, SQLITE3_INTEGER);
            $stmt->execute();
            $qId = $this->db->lastInsertRowID();

            foreach ($options as $index => $optText) {
                if (trim($optText) === '') continue;
                $stmtOpt = $this->db->prepare("INSERT INTO question_options (question_id, option_index, text) VALUES (:qid, :idx, :text)");
                $stmtOpt->bindValue(':qid', $qId, SQLITE3_INTEGER);
                $stmtOpt->bindValue(':idx', $index + 1, SQLITE3_INTEGER); // Indices: 1, 2, 3...
                $stmtOpt->bindValue(':text', $optText, SQLITE3_TEXT);
                $stmtOpt->execute();
            }
            $this->db->exec('COMMIT');
            return true;
        } catch (Exception $e) {
            $this->db->exec('ROLLBACK');
            return false;
        }
    }
    
    public function getQuestions() {
        $result = $this->db->query("SELECT * FROM questions ORDER BY id DESC");
        $questions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['options'] = $this->getOptions($row['id']);
            $questions[] = $row;
        }
        return $questions;
    }

    public function getOptions($questionId) {
        $stmt = $this->db->prepare("SELECT * FROM question_options WHERE question_id = :qid ORDER BY option_index ASC");
        $stmt->bindValue(':qid', $questionId, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $opts = [];
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            $opts[] = $r;
        }
        return $opts;
    }
    
    public function getQuestion($id) {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $row['options'] = $this->getOptions($id);
        }
        return $row;
    }
    
    public function deleteQuestion($id) {
        $this->db->exec("DELETE FROM answers WHERE question_id = $id");
        $this->db->exec("DELETE FROM question_options WHERE question_id = $id");
        $this->db->exec("DELETE FROM question_status WHERE question_id = $id");
        $stmt = $this->db->prepare("DELETE FROM questions WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }

    // --- INSTANCIAS ---
    
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
        $this->db->exec("DELETE FROM answers WHERE survey_code = '$code'");
        $this->db->exec("DELETE FROM question_status WHERE survey_code = '$code'");
        $stmt = $this->db->prepare("DELETE FROM survey_instances WHERE code = :code");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    // --- ESTADO Y TIEMPO ---

    public function setQuestionStatus($surveyCode, $questionId, $status) {
        // Al activar, guardamos timestamp actual
        $startTime = ($status === 'on') ? time() : null;
        
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO question_status (survey_code, question_id, status, start_time, updated_at) 
            VALUES (:survey_code, :question_id, :status, :start, CURRENT_TIMESTAMP)
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':start', $startTime, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function getCurrentStatus($surveyCode) {
        // Obtiene información completa de la pregunta activa y su tiempo
        $stmt = $this->db->prepare("
            SELECT qs.*, q.timer_seconds 
            FROM question_status qs
            JOIN questions q ON qs.question_id = q.id
            WHERE qs.survey_code = :code AND qs.status = 'on'
            ORDER BY qs.updated_at DESC LIMIT 1
        ");
        $stmt->bindValue(':code', $surveyCode, SQLITE3_TEXT);
        $res = $stmt->execute();
        return $res->fetchArray(SQLITE3_ASSOC);
    }
    
    // --- RESPUESTAS ---

    public function saveAnswer($surveyCode, $questionId, $sessionId, $answerIndex) {
        // Verificar tiempo si hay temporizador
        $status = $this->getCurrentStatus($surveyCode);
        if ($status && $status['question_id'] == $questionId && $status['timer_seconds'] > 0) {
            $elapsed = time() - $status['start_time'];
            // Margen de 3 segundos por latencia de red
            if ($elapsed > ($status['timer_seconds'] + 3)) {
                return false; // Tiempo expirado
            }
        }

        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO answers (survey_code, question_id, session_id, answer_index, created_at) 
            VALUES (:survey_code, :question_id, :session_id, :answer, CURRENT_TIMESTAMP)
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
        $stmt->bindValue(':answer', $answerIndex, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    public function getResults($surveyCode, $questionId) {
        // Inicializar contadores a 0 para todas las opciones
        $options = $this->getOptions($questionId);
        $results = [];
        foreach ($options as $opt) {
            $results[$opt['option_index']] = [
                'text' => $opt['text'],
                'count' => 0
            ];
        }

        $stmt = $this->db->prepare("
            SELECT answer_index, COUNT(*) as count 
            FROM answers 
            WHERE survey_code = :survey_code AND question_id = :question_id 
            GROUP BY answer_index
        ");
        $stmt->bindValue(':survey_code', $surveyCode, SQLITE3_TEXT);
        $stmt->bindValue(':question_id', $questionId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $total = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if (isset($results[$row['answer_index']])) {
                $results[$row['answer_index']]['count'] = $row['count'];
                $total += $row['count'];
            }
        }
        
        return ['data' => $results, 'total' => $total];
    }
    
    public function resetAnswers($surveyCode, $questionId) {
        $stmt = $this->db->prepare("DELETE FROM answers WHERE survey_code = :c AND question_id = :q");
        $stmt->bindValue(':c', $surveyCode);
        $stmt->bindValue(':q', $questionId);
        return $stmt->execute();
    }
    
    public function resetAllAnswers($surveyCode) {
        $stmt = $this->db->prepare("DELETE FROM answers WHERE survey_code = :c");
        $stmt->bindValue(':c', $surveyCode);
        return $stmt->execute();
    }
}
?>
