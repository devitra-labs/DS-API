<?php
// scheduler_fetch_data.php
// Run from CLI: php scheduler_fetch_data.php

require_once __DIR__ . '/bootstrap.php'; // autoload, db connection, models, services

// Explicitly include dependencies to avoid autoload issues in this environment
require_once __DIR__ . '/Service/BmkgService.php';
require_once __DIR__ . '/Service/ScraperService.php';
require_once __DIR__ . '/Service/LoggerService.php';
require_once __DIR__ . '/models/SensorModel.php';
require_once __DIR__ . '/models/AlertService.php';
require_once __DIR__ . '/app/Models/BmkgPrakiraanModel.php';
require_once __DIR__ . '/app/Models/BmkgNowcastModel.php';

$pdo = get_pdo(); // DB connection

// Initialize services/models
$bmkgService = new BmkgService();
$scraperService = new ScraperService();
$sensorModel = new SensorModel($pdo);
$alertService = new AlertService($pdo);

$prakiraanModel = new BmkgPrakiraanModel($pdo);
$nowcastModel = new BmkgNowcastModel($pdo);
$logger = new LoggerService();

try {
    $sensors = $sensorModel->getAllActiveWithSource(); // sensors with source_url
} catch (Exception $e) {
    $logger->error("Failed to fetch sensors: " . $e->getMessage());
    error_log("Scheduler: Failed to fetch sensors: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $sensors = [];
}

foreach ($sensors as $sensor) {
    try {
        // Start & Check Data Source
        $sourceType = strtoupper($sensor['source_type'] ?? '');
        $data = null;

        if ($sourceType === 'BMKG_API') {
            // Use admin4 mapping if available; fetch via BMKG publik API
            $adm4 = $sensor['external_id'] ?? null;
            if ($adm4) {
                $data = $bmkgService->fetchPrakiraanCuacaPublic($adm4);

                // Persist prakiraan if applicable
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
                // Best-effort insert; ignore errors to avoid breaking the loop
                try { $prakiraanModel->insert($prakiraanRow); } catch (Exception $e2) { /* ignore */ }

                // Optional: fetch nowcast alerts and store (ignore errors to not block)
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
                            try { $nowcastModel->insert($nowcastRow); } catch (Exception $e3) { /* ignore */ }
                        }
                    }
                } catch (Exception $e) {
                    // ignore nowcast errors to avoid blocking
                }
            } else {
                // If no adm4, attempt to fetch using provided source_url/param
                $data = $bmkgService->fetch($sensor['source_url'] ?? null, $sensor['external_id'] ?? null);
            }
        } else {
            // Scraping path
            $config = $sensorModel->getScrapeConfig($sensor['id']);
            $data = $scraperService->fetch($sensor['source_url'] ?? '', $config);
        }

        // Normalization / Standardized reading
        $reading = [
            'sensor_id' => $sensor['id'],
            'reading_time' => $data['reading_time'] ?? date('Y-m-d H:i:s'),
            'temperature' => $data['temperature'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'wind_speed' => $data['wind_speed'] ?? null,
            'raw_payload' => isset($data['raw_payload']) ? $data['raw_payload'] : $data
        ];

        // Validation: consider fetch/normalization successful if we have at least one data field or a timestamp
        $hasData = !empty($reading['reading_time']) || !empty($reading['temperature']) || !empty($reading['humidity']) || !empty($reading['wind_speed']);
        if (!$hasData) {
            throw new RuntimeException('Data reading invalid or empty after fetch/normalize');
        }

        // Save to database
        $sensorModel->insertReading($reading);
        // Update sensor status to online
        $sensorModel->updateLastSuccess($sensor['id'], $reading['reading_time']);

        // Threshold check
        $alertService->checkThresholds($sensor['id'], $reading);

    } catch (Exception $e) {
        // Robust error handling: log error and mark sensor offline if needed
        $logger->error("Sensor {$sensor['id']} error: " . $e->getMessage());
        error_log("Scheduler: Sensor {$sensor['id']} exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        try {
            $sensorModel->logScrapeError($sensor['id'], $sensor['source_url'], $e->getMessage(), $e->getCode());
        } catch (Exception $logEx) { /* ignore logging errors */ }

        // Determine offline status based on last success
        $lastSuccess = $sensorModel->getLastSuccessAt($sensor['id']);
        $isOffline = true;
        if ($lastSuccess) {
            $diff = time() - strtotime($lastSuccess);
            // If last success within 2 hours, do not mark offline
            if ($diff <= 7200) $isOffline = false;
        }
        if ($isOffline) {
            $sensorModel->setStatus($sensor['id'], 'OFFLINE');
        } else {
            $sensorModel->setStatus($sensor['id'], 'UNKNOWN');
        }
    }
}
?>
