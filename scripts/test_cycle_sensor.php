<?php
// scripts/test_cycle_sensor.php
// Usage: php scripts/test_cycle_sensor.php [sensor_id]

require_once __DIR__ . '/../bootstrap.php';

// Explicit includes for environments without full autoload
require_once __DIR__ . '/../Service/BmkgService.php';
require_once __DIR__ . '/../Service/ScraperService.php';
require_once __DIR__ . '/../Service/LoggerService.php';
require_once __DIR__ . '/../models/SensorModel.php';
require_once __DIR__ . '/../models/AlertService.php';
require_once __DIR__ . '/../app/Models/BmkgPrakiraanModel.php';
require_once __DIR__ . '/../app/Models/BmkgNowcastModel.php';

$pdo = get_pdo();

// Instantiate services/models
$bmkgService = new BmkgService();
$scraperService = new ScraperService();
$sensorModel = new SensorModel($pdo);
$alertService = new AlertService($pdo);
$prakiraanModel = new BmkgPrakiraanModel($pdo);
$nowcastModel = new BmkgNowcastModel($pdo);
$logger = new LoggerService();

// Load sensor either by CLI arg or first available
$sensor = null;
if (isset($argv[1])) {
    $targetId = (int)$argv[1];
    $stmt = $pdo->prepare("SELECT * FROM sensors WHERE id = ?");
    $stmt->execute([$targetId]);
    $sensor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sensor) {
        echo "Sensor with id {$targetId} not found.\n";
        exit(1);
    }
} else {
    $sensors = $sensorModel->getAllActiveWithSource();
    $sensor = $sensors[0] ?? null;
    if (!$sensor) {
        echo "No active sensors with source found.\n";
        exit(1);
    }
}

function normalizeReading($data, $sensor) {
    return [
        'sensor_id' => $sensor['id'],
        'reading_time' => $data['reading_time'] ?? date('Y-m-d H:i:s'),
        'temperature' => $data['temperature'] ?? null,
        'humidity' => $data['humidity'] ?? null,
        'wind_speed' => $data['wind_speed'] ?? null,
        'raw_payload' => isset($data['raw_payload']) ? $data['raw_payload'] : $data
    ];
}

try {
    // Start & Cek Sumber Data
    $sourceType = strtoupper($sensor['source_type'] ?? '');
    $data = null;

    if ($sourceType === 'BMKG_API') {
        $adm4 = $sensor['external_id'] ?? null;
        if ($adm4) {
            $data = $bmkgService->fetchPrakiraanCuacaPublic($adm4);
            // Save prakiraan minimal
            $prakiraanRow = [
                'adm4' => $adm4,
                'desa' => null,
                'cuaca' => $data['cuaca'] ?? null,
                'suhu_min' => $data['suhu_min'] ?? null,
                'suhu_max' => $data['suhu_max'] ?? null,
                'wind_speed' => $data['wind_speed'] ?? null,
                'arah_angin' => $data['arah_angin'] ?? null,
                'waktu_pembaruan' => $data['reading_time'] ?? date('Y-m-d H:i:s'),
                'sumber' => $data['sumber'] ?? 'bmkg_prakiraan_publik'
            ];
            try { $prakiraanModel->insert($prakiraanRow); } catch (Exception $e) { }
            try {
                $nowcast = $bmkgService->fetchNowcastAlerts();
                if (!empty($nowcast['alerts'])) {
                    foreach ($nowcast['alerts'] as $alert) {
                        $nowcastRow = [
                            'adm4' => $adm4,
                            'desa' => null,
                            'peringatan' => $alert,
                            'level' => null,
                            'wilayah_kritis' => null,
                            'waktu_pembaruan' => $nowcast['fetched_at'] ?? date('Y-m-d H:i:s'),
                            'sumber' => 'bmkg_nowcast'
                        ];
                        try { $nowcastModel->insert($nowcastRow); } catch (Exception $e) { }
                    }
                }
            } catch (Exception $e) { }
        } else {
            $data = $bmkgService->fetch($sensor['source_url'] ?? null, $sensor['external_id'] ?? null);
        }
    } else {
        $config = $sensorModel->getScrapeConfig($sensor['id']);
        $data = $scraperService->fetch($sensor['source_url'] ?? '', $config);
    }

    // Normalize
    $reading = normalizeReading($data, $sensor);

    // Validate
    $hasData = !empty($reading['reading_time']) || !empty($reading['temperature']) || !empty($reading['humidity']) || !empty($reading['wind_speed']);
    if (!$hasData) throw new Exception('Data reading invalid.');

    // Save & update
    $sensorModel->insertReading($reading);
    $sensorModel->updateLastSuccess($sensor['id'], $reading['reading_time']);

    // Thresholds
    $alertService->checkThresholds($sensor['id'], $reading);

    echo "Sensor processed successfully. ID: {$sensor['id']}, time: {$reading['reading_time']}\n";
} catch (Exception $e) {
    $logger->error("Test cycle error for sensor {$sensor['id']}: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>
