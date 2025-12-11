<?php
// ---------------------------------------------------------
// 1. CONFIG ANTI-STRESS (WAJIB UTK API JSON)
// ---------------------------------------------------------
// Matikan tampilan error di layar agar JSON tidak rusak oleh teks "Deprecated"
ini_set('display_errors', 0); 
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

if (!class_exists('Database')) {
    class Database {
        private $host = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
        private $port = 4000;
        private $db_name = "test";
        private $username = "4DQTpKbtm8cDVGS.root";
        private $password = "K5v9ZuijXLolryVJ";
        private $charset = "utf8mb4";

        public function getConnection() {
            $pdo = null;
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
            
            if (!empty($this->charset)) {
                $dsn .= ";charset=" . $this->charset;
            }

            //$ssl_ca = __DIR__ . '/cacert.pem';
            $ssl_ca = '/etc/ssl/certs/tidb-cloud.pem';
            
            // ---------------------------------------------------------
            // 2. LOGIKA DETEKSI VERSI PHP (AMAN)
            // ---------------------------------------------------------
            // Kita cek dulu: Apakah konstanta BARU (PHP 8.4+) tersedia?
            
            $opts = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            // --- SETTING SSL VERIFY ---
            // Cek apakah Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT ada (PHP 8.4+)
            if (defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
                $opts[constant('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = false;
            } else {
                // Fallback ke Integer 1014 untuk PHP lama (menghindari warning nama usang)
                $opts[1014] = false; 
            }

            // --- SETTING SSL CA ---
            if (file_exists($ssl_ca)) {
                // Cek apakah Pdo\Mysql::ATTR_SSL_CA ada (PHP 8.4+)
                if (defined('Pdo\Mysql::ATTR_SSL_CA')) {
                    $opts[constant('Pdo\Mysql::ATTR_SSL_CA')] = $ssl_ca;
                } else {
                    // Fallback ke Integer 1012
                    $opts[1012] = $ssl_ca;
                }
            }

            try {
                $pdo = new PDO($dsn, $this->username, $this->password, $opts);
                return $pdo;
            } catch (PDOException $e) {
                // Pastikan respon error tetap dalam format JSON
                header('Content-Type: application/json');
                // Http response code 500 (Internal Server Error)
                http_response_code(500); 
                echo json_encode([
                    "status" => "error", 
                    "message" => "Database Connection Failed: " . $e->getMessage()
                ]);
                exit; // Stop script di sini agar tidak ada output lain
            }
        }
    }
}
?>