<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'ilsaaftg_drug_search');
define('DB_PASS', 'Ssh918273@');
define('DB_NAME', 'ilsaaftg_drug_search');
define('CHARSET', 'utf8mb4');

class Database {
    private $connection;
    private static $instance = null;

    private function __construct() {
        try {
            $this->connection = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // دالة للاستعلام مع الباراميترات
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            die('خطأ في الاستعلام: ' . $e->getMessage());
        }
    }

    // دالة لجلب صف واحد
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    // دالة لجلب مجموعة من الصفوف
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    // دالة للإدراج
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        $this->query($sql, array_values($data));
        return $this->connection->lastInsertId();
    }

    // دالة للتحديث
    public function update($table, $data, $where, $whereParams = []) {
        $setClauses = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $setClauses[] = "$key = ?";
            $params[] = $value;
        }
        
        $setClause = implode(', ', $setClauses);
        $sql = "UPDATE $table SET $setClause WHERE $where";
        
        $this->query($sql, array_merge($params, $whereParams));
    }

    // دالة للحذف
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $this->query($sql, $params);
    }
}