<?php
class BmkgNowcastModel {
    protected $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function insert(array $row) {
        $sql = "INSERT INTO nowcast_alerts (
            adm4, desa, peringatan, level, wilayah_kritis, waktu_pembaruan, sumber, tcc
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $row['adm4'] ?? null,
            $row['desa'] ?? null,
            $row['peringatan'] ?? null,
            $row['level'] ?? null,
            $row['wilayah_kritis'] ?? null,
            $row['waktu_pembaruan'] ?? null,
            $row['sumber'] ?? null,
            $row['tcc'] ?? null
        ]);
    }
}
