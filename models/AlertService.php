<?php
class AlertService {
    protected $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function checkThresholds($sensorId, $reading) {
        // ambil thresholds
        $stmt = $this->pdo->prepare("SELECT * FROM sensor_thresholds WHERE sensor_id = ?");
        $stmt->execute([$sensorId]);
        $thresholds = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($thresholds as $t) {
            $field = $t['field_name'];
            if (!isset($reading[$field])) continue;
            $val = $reading[$field];
            $expr = null;
            switch ($t['operator']) {
                case '>':  $expr = ($val > $t['value']); break;
                case '<':  $expr = ($val < $t['value']); break;
                case '>=': $expr = ($val >= $t['value']); break;
                case '<=': $expr = ($val <= $t['value']); break;
                case '=':  $expr = ($val == $t['value']); break;
                case '!=': $expr = ($val != $t['value']); break;
            }
            if ($expr) {
                $this->emitAlert($sensorId, $field, $val, $t);
            }
        }
    }

    protected function emitAlert($sensorId, $field, $value, $threshold) {
        // contoh: insert ke alerts table atau kirim webhook/email
        $stmt = $this->pdo->prepare("INSERT INTO alerts (sensor_id, field_name, value, threshold_value, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$sensorId, $field, $value, $threshold['value']]);
        // juga bisa push notif via REST webhook
    }
}
