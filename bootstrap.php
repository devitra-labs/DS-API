<?php

// ====== Basic helpers ======
if (!function_exists('base_path')) {
    function base_path($path = '') {
        return __DIR__ . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        static $vars;
        if ($vars === null) {
            $vars = [];
            $envFile = base_path('.env');
            if (file_exists($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    [$name, $value] = array_pad(explode('=', $line, 2), 2, null);
                    $vars[trim($name)] = trim($value);
                }
            }
        }
        return $vars[$key] ?? $default;
    }
}

// ====== Autoloader (very simple) ======
spl_autoload_register(function($class) {
    $paths = [
        base_path("app/Models/{$class}.php"),
        base_path("app/Services/{$class}.php"),
        base_path("app/Controllers/{$class}.php"),
        base_path("app/{$class}.php"),
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

    // Try config from file first
    $configPath = base_path("config/database.php");
    if (file_exists($configPath)) {
        require_once $configPath; // may define Database class or not
    }

    // If a Database class exists, use it
    if (class_exists('Database')) {
        try {
            $pdo = (new Database())->getConnection();
            return $pdo;
        } catch (Exception $e) {
            // fall through to env fallback
        }
    }

    // Fallback to environment-based config
    $host = env('DB_HOST', 'localhost');
    $dbName = env('DB_NAME', 'desaverse');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $charset = env('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host=$host;dbname=$dbName";
    if (!empty($charset)) {
        $dsn .= ";charset=$charset";
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        // Fallback: try localhost TCP if socket / default failed
        try {
            $pdo = new PDO("mysql:host=127.0.0.1;dbname=$dbName", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e2) {
            die("Database connection failed: " . $e2->getMessage());
        }
    }

    return $pdo;
}

// ====== App bootstrap defaults ======
date_default_timezone_set('Asia/Jakarta');

// Debug mode: enable detailed errors if APP_DEBUG=1
$__debug = (int)(function_exists('env') ? env('APP_DEBUG', 0) : 0);
if ($__debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

