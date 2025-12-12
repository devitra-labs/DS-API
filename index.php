<?php
// ---------------------------------------------------------
// 1. CONFIG HEADER (CORS & JSON)
// ---------------------------------------------------------
error_reporting(0); 
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle Preflight Request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ---------------------------------------------------------
// 2. INCLUDE CONTROLLER & SERVICES
// ---------------------------------------------------------

// Load AuthController
if (file_exists('AuthController.php')) {
    include_once 'AuthController.php';
} elseif (file_exists('controller/AuthController.php')) {
    include_once 'controller/AuthController.php';
}

// Load BmkgService
// Pastikan file BmkgService.php ada di folder Service
if (file_exists('Service/BmkgService.php')) {
    require_once 'Service/BmkgService.php';
} elseif (file_exists('services/BmkgService.php')) {
    require_once 'services/BmkgService.php';
}

// ---------------------------------------------------------
// 3. UNIVERSAL INPUT PARSER (JSON / POST / GET)
// ---------------------------------------------------------

// A. Ambil Action
$action = isset($_GET['action']) ? $_GET['action'] : '';

// B. Ambil Data Body (JSON)
$jsonInput = file_get_contents("php://input");
$data = json_decode($jsonInput);

// C. Fallback ke POST Form Data jika JSON kosong
if (is_null($data)) {
    $data = (object) $_POST;
}

// D. Gabungkan parameter GET (Query Params) agar fleksibel
// Ini memungkinkan request seperti: ?action=bmkg_prakiraan&adm4=35.15.01.2001
$params = ['email', 'password', 'name', 'adm4', 'desa'];
foreach ($params as $param) {
    if (empty($data->$param) && isset($_GET[$param])) {
        $data->$param = $_GET[$param];
    }
}

// ---------------------------------------------------------
// 4. ROUTING / SWITCH CASE
// ---------------------------------------------------------

// Inisialisasi Controller
$auth = class_exists('AuthController') ? new AuthController() : null;

switch ($action) {
    
    // --- AUTHENTICATION (FIREBASE) ---
    case 'register':
        if ($auth) $auth->register($data);
        else sendError("AuthController not found");
        break;

    case 'login':
        if ($auth) $auth->login($data);
        else sendError("AuthController not found");
        break;

    // --- BMKG: PRAKIRAAN CUACA (BY KODE ADM4) ---
    // Contoh: ?action=bmkg_prakiraan&adm4=35.78.01.1002
    case 'bmkg_prakiraan':
        if (empty($data->adm4)) {
            sendError("Parameter 'adm4' (Kode Wilayah) wajib diisi.", 400);
        } else {
            handleBmkgRequest(function($bmkg) use ($data) {
                return $bmkg->fetchPrakiraanCuacaPublic($data->adm4);
            });
        }
        break;

    // --- BMKG: NOWCAST / PERINGATAN DINI ---
    // Contoh: ?action=bmkg_nowcast
    case 'bmkg_nowcast':
        handleBmkgRequest(function($bmkg) {
            return $bmkg->fetchNowcastAlerts();
        });
        break;

    // --- BMKG: PRAKIRAAN CUACA (BY NAMA DESA) ---
    // Contoh: ?action=bmkg_prakiraan_desa&desa=Sukamaju
    // Butuh file config/admin4_lookup.json
    case 'bmkg_prakiraan_desa':
        if (empty($data->desa)) {
            sendError("Parameter 'desa' wajib diisi.", 400);
        } else {
            handleBmkgRequest(function($bmkg) use ($data) {
                // 1. Cari kode ADM4 berdasarkan nama desa
                $adm4 = $bmkg->resolveAdmin4ForDesa($data->desa);
                
                if (!$adm4) {
                    throw new Exception("Desa '{$data->desa}' tidak ditemukan dalam database lookup.");
                }

                // 2. Ambil cuaca berdasarkan kode ADM4 yang ditemukan
                $weather = $bmkg->fetchPrakiraanCuacaPublic($adm4);
                
                // 3. Gabungkan hasil
                return [
                    'desa_request' => $data->desa,
                    'adm4_found' => $adm4,
                    'data' => $weather
                ];
            });
        }
        break;

    default:
        sendError("Endpoint tidak ditemukan. Gunakan ?action=register, login, bmkg_prakiraan, atau bmkg_nowcast", 404);
        break;
}

// ---------------------------------------------------------
// 5. HELPER FUNCTIONS
// ---------------------------------------------------------

function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode(["status" => "error", "message" => $message]);
    exit();
}

/**
 * Wrapper function untuk menangani Request BMKG dengan Try-Catch
 */
function handleBmkgRequest($callback) {
    // Cek apakah class BmkgService ada
    if (!class_exists('BmkgService')) {
        sendError("Service BMKG tidak ditemukan / file belum di-include.");
        return;
    }

    try {
        $bmkg = new BmkgService();
        $result = $callback($bmkg); // Jalankan fungsi spesifik
        echo json_encode($result);
    } catch (Exception $e) {
        // Tangkap error dari BmkgService (misal: Koneksi timeout / Data kosong)
        sendError("BMKG Error: " . $e->getMessage());
    }
}
?>