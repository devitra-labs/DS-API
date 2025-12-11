<?php
// =======================================================================
// 1. KONFIGURASI DEBUGGING (PENTING UNTUK RAILWAY)
// =======================================================================
// Matikan tampilan error di browser (agar JSON tidak rusak)
ini_set('display_errors', 0); 
ini_set('display_startup_errors', 0);

// Nyalakan pencatatan log sistem
ini_set('log_errors', 1); 
error_reporting(E_ALL);

// KIRIM LOG KE OUTPUT CONSOLE RAILWAY
// Ini kuncinya: agar kamu bisa baca errornya di menu "Logs" Railway
ini_set('error_log', '/dev/stderr'); 

// =======================================================================
// 2. HEADER CORS & PREFLIGHT
// =======================================================================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle request OPTIONS (Preflight) agar tidak lanjut ke logic bawah
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // =======================================================================
    // 3. LOAD FILE DENGAN ABSOLUTE PATH (ANTI ERROR DOCKER)
    // =======================================================================
    
    // Tentukan path file penting
    $pathAuthController = __DIR__ . '/controller/AuthController.php';
    $pathBootstrap      = __DIR__ . '/bootstrap.php';

    // Cek keberadaan file sebelum di-load (Debugging)
    if (!file_exists($pathAuthController)) {
        throw new Exception("CRITICAL: File AuthController tidak ditemukan di: " . $pathAuthController);
    }
    if (!file_exists($pathBootstrap)) {
        throw new Exception("CRITICAL: File Bootstrap tidak ditemukan di: " . $pathBootstrap);
    }

    // Load File
    include_once $pathAuthController;
    include_once $pathBootstrap;

    // =======================================================================
    // 4. KONEKSI DATABASE
    // =======================================================================
    // Pastikan fungsi get_pdo ada (biasanya dari bootstrap.php)
    if (!function_exists('get_pdo')) {
        throw new Exception("CRITICAL: Fungsi get_pdo() tidak ditemukan. Cek isi bootstrap.php");
    }

    $pdo = get_pdo(); 

} catch (Exception $e) {
    // TANGKAP ERROR SETUP / KONEKSI
    error_log("SETUP ERROR: " . $e->getMessage()); // Masuk ke Log Railway
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Server Setup Error (Cek Logs)", 
        "debug_info" => $e->getMessage()
    ]);
    exit();
}

// =======================================================================
// 5. ROUTING & LOGIC
// =======================================================================

// Ambil parameter action
$action = $_REQUEST['action'] ?? '';

// Jika action kosong, beri respons default (Health Check)
if (empty($action)) {
    echo json_encode(["status" => "ok", "message" => "API Ready. Available actions: register, login, bmkg_..."]);
    exit();
}

// Inisialisasi Controller
$auth = new AuthController(); 

switch ($action) {
    // --- AUTHENTICATION ---
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
        // Support login via GET (URL) atau POST (Form)
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

    // --- BMKG SERVICES ---
    
    case 'bmkg_prakiraan':
        $adm4 = $_REQUEST['adm4'] ?? '';
        if (empty($adm4)) {
            http_response_code(400);
            echo json_encode(['message' => 'adm4 parameter is required']);
            break;
        }
        
        // Gunakan __DIR__ untuk include service
        $servicePath = __DIR__ . '/Service/BmkgService.php';
        if (file_exists($servicePath)) {
            require_once $servicePath;
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'File Service BmkgService.php tidak ditemukan']);
            break;
        }

        try {
            $bmkg = new BmkgService();
            $result = $bmkg->fetchPrakiraanCuacaPublic($adm4);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("BMKG Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'BMKG error: ' . $e->getMessage()]);
        }
        break;

    case 'bmkg_nowcast':
        $servicePath = __DIR__ . '/Service/BmkgService.php';
        require_once $servicePath;
        try {
            $bmkg = new BmkgService();
            $result = $bmkg->fetchNowcastAlerts();
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("BMKG Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'BMKG error: ' . $e->getMessage()]);
        }
        break;

    case 'bmkg_prakiraan_desa':
        $desa = $_REQUEST['desa'] ?? '';
        if (empty($desa)) {
            http_response_code(400);
            echo json_encode(['message' => 'desa parameter is required']);
            break;
        }
        require_once __DIR__ . '/Service/BmkgService.php';
        try {
            $bmkg = new BmkgService();
            $adm4 = $bmkg->resolveAdmin4ForDesa($desa);
            if (!$adm4) {
                http_response_code(404);
                echo json_encode(['message' => 'adm4 tidak ditemukan untuk desa ini']);
                break;
            }
            $prakiraan = $bmkg->fetchPrakiraanCuacaPublic($adm4);
            $nowcast = $bmkg->fetchNowcastAlerts();
            $response = [
                'desa' => $desa,
                'adm4' => $adm4,
                'prakiraan' => $prakiraan,
                'nowcast_alerts' => $nowcast
            ];
            echo json_encode($response);
        } catch (Exception $e) {
            error_log("BMKG Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'BMKG error: ' . $e->getMessage()]);
        }
        break;

    case 'bmkg_latest':
        $adm4Val = $_REQUEST['adm4'] ?? '';
        if (empty($adm4Val)) {
            http_response_code(400);
            echo json_encode(['message' => 'adm4 parameter is required']);
            break;
        }
        try {
            // Gunakan __DIR__ untuk Models
            require_once __DIR__ . '/app/Models/BmkgPrakiraanModel.php';
            require_once __DIR__ . '/app/Models/BmkgNowcastModel.php';
            
            $prakiraanModel = new BmkgPrakiraanModel($pdo);
            $nowcastModel = new BmkgNowcastModel($pdo);
            
            $prakiraanLatest = $prakiraanModel->getLatestByAdm4($adm4Val);
            $nowcastLatest  = $nowcastModel->getLatestByAdm4($adm4Val);
            $resp = [
                'adm4' => $adm4Val,
                'prakiraan_latest' => $prakiraanLatest,
                'nowcast_latest' => $nowcastLatest
            ];
            echo json_encode($resp);
        } catch (Exception $e) {
            error_log("BMKG Latest Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'BMKG latest error: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'Endpoint action tidak ditemukan: ' . $action]);
        break;
}
?>