<?php
// ------------------------------------------------------------------
// 1. CONFIG DEBUG (NYALAKAN INI AGAR ERROR MUNCUL DI LOG RAILWAY)
// ------------------------------------------------------------------
// Jangan matikan error reporting saat sedang debugging!
// Kita arahkan error ke "stderr" agar terbaca di Log Railway
ini_set('display_errors', 0); // Tetap 0 agar JSON tidak rusak
ini_set('log_errors', 1);     // Nyalakan pencatatan log
ini_set('error_log', '/dev/stderr'); // Kirim log ke output console Railway
error_reporting(E_ALL);       // Laporkan SEMUA jenis error

/**
 * PENTING: Header CORS
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // ------------------------------------------------------------------
    // 2. PERBAIKAN PATH FILE (GUNAKAN __DIR__)
    // ------------------------------------------------------------------
    // Menggunakan __DIR__ memastikan PHP mencari file dari folder tempat index.php berada
    // Ini WAJIB di Docker/Linux agar tidak tersesat.
    
    $pathController = __DIR__ . '/controller/AuthController.php';
    $pathBootstrap  = __DIR__ . '/bootstrap.php';

    if (!file_exists($pathController)) {
        throw new Exception("File Controller tidak ditemukan di: " . $pathController);
    }
    if (!file_exists($pathBootstrap)) {
        throw new Exception("File Bootstrap tidak ditemukan di: " . $pathBootstrap);
    }

    include_once $pathController;
    include_once $pathBootstrap;

    // ------------------------------------------------------------------
    // 3. KONEKSI DB
    // ------------------------------------------------------------------
    // Pastikan fungsi get_pdo() benar-benar ada di bootstrap.php
    if (!function_exists('get_pdo')) {
        throw new Exception("Fungsi get_pdo() tidak ditemukan. Cek file bootstrap.php Anda.");
    }

    $pdo = get_pdo(); 

} catch (Exception $e) {
    // Tangkap error setup awal (file hilang / db connect)
    error_log("CRITICAL ERROR: " . $e->getMessage()); // Catat ke Log Railway
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit();
}

// --- AMBIL PARAMETER ACTION ---
$action = $_REQUEST['action'] ?? '';

// Debugging sederhana: Jika action kosong, beri tahu server hidup
if (empty($action)) {
    echo json_encode(["message" => "API Ready. Silakan pilih action."]);
    exit();
}

$auth = new AuthController(); 

// --- ROUTING ---
switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             $data = (object) $_POST;
             if (empty($data->email) || empty($data->password)) {
                 http_response_code(400);
                 echo json_encode(['message' => 'Email dan Password wajib diisi']);
                 exit();
             }
             $auth->register($data);
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Register harus menggunakan POST']);
        }
        break;

    case 'login':
        $email = $_REQUEST['email'] ?? '';
        $password = $_REQUEST['password'] ?? '';

        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Email dan password tidak boleh kosong'
            ]);
            exit();
        }

        $loginData = (object) [
            'email' => $email,
            'password' => $password
        ];

        $auth->login($loginData);
        break;

    // --- BAGIAN BMKG ---
    case 'bmkg_prakiraan':
        $adm4 = $_REQUEST['adm4'] ?? '';
        if (empty($adm4)) {
            http_response_code(400);
            echo json_encode(['message' => 'adm4 parameter is required']);
            break;
        }
        // Gunakan __DIR__ juga di sini
        require_once __DIR__ . '/Service/BmkgService.php';
        try {
            $bmkg = new BmkgService();
            $result = $bmkg->fetchPrakiraanCuacaPublic($adm4);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'BMKG error: ' . $e->getMessage()]);
        }
        break;

    // ... (Case lainnya biarkan saja, tapi pastikan require pakai __DIR__) ...
    
    default:
        http_response_code(404);
        echo json_encode(['message' => 'Endpoint action tidak ditemukan: ' . $action]);
        break;
}
?>