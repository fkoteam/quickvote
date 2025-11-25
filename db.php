<?php
// db.php - Adaptado para MySQL con PDO
define('DB_HOST', 'localhost');
define('DB_NAME', 'survey_db');
define('DB_USER', 'root'); // Cambiar por tu usuario
define('DB_PASS', '');     // Cambiar por tu contraseña
define('DB_CHARSET', 'utf8mb4');

class Database {
    private $pdo;

    public function __construct() {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            die("Error de conexión a MySQL: " . $e->getMessage());
        }
    }

    // CRUD de preguntas
    public function createQuestion($text, $numOptions = 2, $optionLabels = '["SÍ","NO"]', $autoClose = 0, $closeSeconds = 30) {
        $stmt = $this->pdo->prepare("
            INSERT INTO questions (text, num_options, option_labels, auto_close, close_seconds) 
            VALUES (:text, :num_options, :option_labels, :auto_close, :close_seconds)
        ");
        return $stmt->execute([
            ':text' => $text,
            ':num_options' => $numOptions,
            ':option_labels' => $optionLabels,
            ':auto_close' => $autoClose,
            ':close_seconds' => $closeSeconds
        ]);
    }
    
    public function getQuestions() {
        return $this->pdo->query("SELECT * FROM questions ORDER BY id")->fetchAll();
    }
    
    public function getQuestion($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM questions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    public function updateQuestion($id, $text, $numOptions, $optionLabels, $autoClose, $closeSeconds) {
        $stmt = $this->pdo->prepare("
            UPDATE questions 
            SET text = :text, num_options = :num_options, option_labels = :option_labels,
                auto_close = :auto_close, close_seconds = :close_seconds
            WHERE id = :id
        ");
        return $stmt->execute([
            ':text' => $text, ':num_options' => $numOptions, ':option_labels' => $optionLabels,
            ':auto_close' => $autoClose, ':close_seconds' => $closeSeconds, ':id' => $id
        ]);
    }
    
    public function deleteQuestion($id) {
        $stmt = $this->pdo->prepare("DELETE FROM questions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    
    // Instancias
    public function createSurveyInstance($code) {
        $stmt = $this->pdo->prepare("INSERT INTO survey_instances (code) VALUES (:code)");
        return $stmt->execute([':code' => $code]);
    }
    
    public function getSurveyInstances() {
        return $this->pdo->query("SELECT * FROM survey_instances ORDER BY created_at DESC")->fetchAll();
    }
    
    public function surveyExists($code) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM survey_instances WHERE code = :code");
        $stmt->execute([':code' => $code]);
        return $stmt->fetch()['count'] > 0;
    }
    
    public function deleteSurveyInstance($code) {
        $this->pdo->prepare("DELETE FROM answers WHERE survey_code = :code")->execute([':code' => $code]);
        $this->pdo->prepare("DELETE FROM question_status WHERE survey_code = :code")->execute([':code' => $code]);
        return $this->pdo->prepare("DELETE FROM survey_instances WHERE code = :code")->execute([':code' => $code]);
    }
    
    // Estado
    public function setQuestionStatus($surveyCode, $questionId, $status) {
        $startedAt = $status === 'on' ? date('Y-m-d H:i:s') : null;
        // MySQL UPSERT syntax
        $stmt = $this->pdo->prepare("
            INSERT INTO question_status (survey_code, question_id, status, started_at, updated_at) 
            VALUES (:survey_code, :question_id, :status, :started_at, NOW())
            ON DUPLICATE KEY UPDATE status = :status_u, started_at = :started_at_u, updated_at = NOW()
        ");
        return $stmt->execute([
            ':survey_code' => $surveyCode, ':question_id' => $questionId, 
            ':status' => $status, ':started_at' => $startedAt,
            ':status_u' => $status, ':started_at_u' => $startedAt
        ]);
    }
    
    public function getQuestionStatus($surveyCode, $questionId) {
        $stmt = $this->pdo->prepare("SELECT status, started_at FROM question_status WHERE survey_code = :c AND question_id = :q");
        $stmt->execute([':c' => $surveyCode, ':q' => $questionId]);
        $row = $stmt->fetch();
        return $row ? $row : ['status' => 'off', 'started_at' => null];
    }
    
    public function getCurrentQuestion($surveyCode) {
        $stmt = $this->pdo->prepare("
            SELECT question_id, started_at FROM question_status 
            WHERE survey_code = :code AND status = 'on'
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->execute([':code' => $surveyCode]);
        return $stmt->fetch();
    }
    
    // Respuestas (MODIFICADO PARA EMAIL)
    public function saveAnswer($surveyCode, $questionId, $sessionId, $answer, $email = null) {
        // MySQL UPSERT syntax
        $stmt = $this->pdo->prepare("
            INSERT INTO answers (survey_code, question_id, session_id, answer, email, created_at) 
            VALUES (:code, :qid, :sid, :ans, :email, NOW())
            ON DUPLICATE KEY UPDATE answer = :ans_u, email = :email_u, created_at = NOW()
        ");
        return $stmt->execute([
            ':code' => $surveyCode, ':qid' => $questionId, ':sid' => $sessionId, 
            ':ans' => $answer, ':email' => $email,
            ':ans_u' => $answer, ':email_u' => $email
        ]);
    }
    
    public function getResults($surveyCode, $questionId) {
        $stmt = $this->pdo->prepare("SELECT answer, COUNT(*) as count FROM answers WHERE survey_code = :c AND question_id = :q GROUP BY answer");
        $stmt->execute([':c' => $surveyCode, ':q' => $questionId]);
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[$row['answer']] = $row['count'];
        }
        return $results;
    }
    
    public function resetAnswers($surveyCode, $questionId) {
        $stmt = $this->pdo->prepare("DELETE FROM answers WHERE survey_code = :c AND question_id = :q");
        return $stmt->execute([':c' => $surveyCode, ':q' => $questionId]);
    }
    
    public function resetAllAnswers($surveyCode) {
        $stmt = $this->pdo->prepare("DELETE FROM answers WHERE survey_code = :c");
        return $stmt->execute([':c' => $surveyCode]);
    }

    // Funciones Helper para envío masivo (NUEVO)
    public function getEmailsByInstance($code) {
        $stmt = $this->pdo->prepare("SELECT DISTINCT email FROM answers WHERE survey_code = :code AND email IS NOT NULL AND email != ''");
        $stmt->execute([':code' => $code]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getUserAnswer($code, $questionId, $email) {
        $stmt = $this->pdo->prepare("SELECT answer FROM answers WHERE survey_code = :code AND question_id = :qid AND email = :email LIMIT 1");
        $stmt->execute([':code' => $code, ':qid' => $questionId, ':email' => $email]);
        return $stmt->fetchColumn();
    }
    
    // Método helper para ejecutar SQL directo (para install.php)
    public function exec($sql) {
        return $this->pdo->exec($sql);
    }
}
?>
