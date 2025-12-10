<?php
class SensorModel {
    protected $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function getAllActiveWithSource(): array {
        $stmt = $this->pdo->prepare("SELECT * FROM sensors WHERE source_url IS NOT NULL AND source_url != ''");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getScrapeConfig($sensorId) {
        // contoh: ambil config dari column json atau tabel terpisah
        $stmt = $this->pdo->prepare("SELECT scrape_config FROM sensors WHERE id = ?");
        $stmt->execute([$sensorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? json_decode($row['scrape_config'], true) : [];
    }

    public function insertReading(array $r) {
        $sql = "INSERT INTO sensor_readings (sensor_id, reading_time, temperature, humidity, wind_speed, raw_payload)
                VALUES (:sensor_id, :reading_time, :temperature, :humidity, :wind_speed, :raw_payload)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sensor_id' => $r['sensor_id'],
            ':reading_time' => $r['reading_time'],
            ':temperature' => $r['temperature'],
            ':humidity' => $r['humidity'],
            ':wind_speed' => $r['wind_speed'],
            ':raw_payload' => $r['raw_payload']
        ]);
    }

    public function updateLastSuccess($sensorId, $time) {
        $stmt = $this->pdo->prepare("UPDATE sensors SET last_success_at = ?, status = 'OK' WHERE id = ?");
        $stmt->execute([$time, $sensorId]);
    }

    public function getLastSuccessAt($sensorId) {
        $stmt = $this->pdo->prepare("SELECT last_success_at FROM sensors WHERE id = ?");
        $stmt->execute([$sensorId]);
        return $stmt->fetchColumn();
    }

    public function logScrapeError($sensorId, $url, $message, $httpStatus = null) {
        $stmt = $this->pdo->prepare("INSERT INTO scrape_errors (sensor_id, source_url, error_message, http_status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sensorId, $url, $message, $httpStatus]);
    }

    public function setStatus($sensorId, $status) {
        $stmt = $this->pdo->prepare("UPDATE sensors SET status = ? WHERE id = ?");
        $stmt->execute([$status, $sensorId]);
    }
}
