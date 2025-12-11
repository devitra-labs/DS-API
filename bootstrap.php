<?php

// ====== Basic helpers ======
if (!function_exists('base_path')) {
    function base_path($path = '') {
        // __DIR__ adalah root project (karena bootstrap.php ada di root)
        return __DIR__ . ($path ? '/' . ltrim($path, '/') : '');
    }
}

// ====== ENV Helper untuk Railway ======
if (!function_exists('env')) {
    function env($key, $default = null) {
        // 1. Cek Environment Variable Server (WAJIB untuk Railway)
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        // 2. Fallback ke file .env lokal (jika ada)
        static $vars;
        if ($vars === null) {
            $vars = [];
            $envFile = base_path('.env');
            if (file_exists($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    [$name, $val] = array_pad(explode('=', $line, 2), 2, null);
                    $vars[trim($name)] = trim($val);
                }
            }
        }
        return $vars[$key] ?? $default;
    }
}

// ====== Autoloader ======
spl_autoload_register(function($class) {
    // Ubah backslash (\) namespace menjadi slash (/)
    $classPath = str_replace('\\', '/', $class);
    
    $paths = [
        base_path("app/Models/{$classPath}.php"),
        base_path("app/Services/{$classPath}.php"),
        base_path("app/Controllers/{$classPath}.php"),
        base_path("controller/{$classPath}.php"), 
        base_path("app/{$classPath}.php"),
    ];
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ====== PDO connection setup ======
function get_pdo() {
    static $pdo;
    if ($pdo !== null) return $pdo;

    // 1. Load file config/database.php
    // Sesuai info Anda: file ada di folder config
    $configPath = base_path("config/database.php");
    
    if (file_exists($configPath)) {
        require_once $configPath; 
    } else {
        // Cek fallback jika ternyata file ada di root (jaga-jaga)
        $rootPath = base_path("database.php");
        if (file_exists($rootPath)) {
            require_once $rootPath;
        } else {
            error_log("WARNING: File database.php tidak ditemukan di config/ maupun root.");
        }
    }

    // 2. Gunakan Class Database (Prioritas Utama untuk TiDB)
    if (class_exists('Database')) {
        // JANGAN PAKAI TRY-CATCH DI SINI
        // Biarkan errornya "meledak" agar tertangkap oleh index.php dan masuk Log Railway
        // Ini satu-satunya cara kita tahu kalau sertifikat SSL salah path atau password salah.
        $db = new Database();
        return $db->getConnection();
    }

    // 3. Logic Fallback (Hanya jalan jika class Database TIDAK ADA)
    // Jika class Database ada tapi error, kode di atas sudah stop.
    // Kode bawah ini tidak aman untuk TiDB Cloud karena biasanya tidak pakai SSL
    
    $host = env('DB_HOST', 'localhost');
    $dbName = env('DB_NAME', 'desaverse');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $charset = env('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host=$host;dbname=$dbName;charset=$charset";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Lempar error agar muncul di Log
        throw new Exception("Koneksi Fallback Gagal: " . $e->getMessage());
    }
}

// ====== App bootstrap defaults ======
date_default_timezone_set('Asia/Jakarta');

// Setup Error Log untuk Railway
ini_set('log_errors', 1);
ini_set('error_log', '/dev/stderr');

$__debug = (int)(function_exists('env') ? env('APP_DEBUG', 0) : 0);
if ($__debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}