<?php
if (!class_exists('Database')) {
class Database {
    private $host = "sql304.infinityfree.com";
    private $db_name = "if0_40505287_desaverse";
    private $username = "if0_40505287"; // Default XAMPP
    private $password = "MiKVqv4pj7"; // Default password, adjust as needed
    private $charset = "utf8mb4";

    public function getConnection() {
        $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name;
        if (!empty($this->charset)) {
            $dsn .= ";charset=" . $this->charset;
        }
        try {
            $pdo = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            // Fallback: try without charset if charset causes issues
            try {
                $pdo = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                return $pdo;
            } catch (PDOException $e2) {
                die("Database connection failed: " . $e2->getMessage());
            }
        }
    }
}
}
?>