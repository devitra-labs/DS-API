<?php
    
error_reporting(0); 
ini_set('display_errors', 0);
/**
 * PENTING: Header CORS harus paling atas.
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// MENANGANI PREFLIGHT REQUEST
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- INCLUDE FILE ---
// Pastikan path folder controller dan bootstrap benar
// Di InfinityFree, strukturnya biasanya: htdocs/api/controller/AuthController.php
include_once 'controller/AuthController.php';
include_once __DIR__ . '/bootstrap.php';

// --- KONEKSI DB ---
try {
    $pdo = get_pdo(); 
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database Connection Failed", "error" => $e->getMessage()]);
    exit();
}

// --- AMBIL PARAMETER ACTION ---
// Kita pakai $_REQUEST supaya kamu bisa tes lewat Link Browser (GET) maupun Form (POST)
$action = $_REQUEST['action'] ?? '';

// --- CONTROLLER ---
$auth = new AuthController(); 

// --- ROUTING ---
switch ($action) {
    case 'register':
        // Register sebaiknya tetap POST demi keamanan
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
        // KITA UBAH LOGIC INI AGAR BISA DITES LEWAT LINK BROWSER
        
        // 1. Ambil data dari URL atau FORM
        $email = $_REQUEST['email'] ?? '';
        $password = $_REQUEST['password'] ?? '';

        // 2. Validasi
        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Email dan password tidak boleh kosong'
            ]);
            exit();
        }

        // 3. Bungkus jadi object
        $loginData = (object) [
            'email' => $email,
            'password' => $password
        ];

        // 4. Jalankan Login
        $auth->login($loginData);
        break;

    // --- BAGIAN BMKG (TETAP SAMA) ---
    
    case 'bmkg_prakiraan':
        $adm4 = $_REQUEST['adm4'] ?? '';
        if (empty($adm4)) {
            http_response_code(400);
            echo json_encode(['message' => 'adm4 parameter is required']);
            break;
        }
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

    case 'bmkg_nowcast':
        require_once __DIR__ . '/Service/BmkgService.php';
        try {
            $bmkg = new BmkgService();
            $result = $bmkg->fetchNowcastAlerts();
            echo json_encode($result);
        } catch (Exception $e) {
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
            http_response_code(500);
            echo json_encode(['message' => 'BMKG latest error: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'Endpoint action tidak ditemukan']);
        break;
}
?>