<?php
class BmkgPrakiraanModel {
    protected $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function insert(array $row) {
        $sql = "INSERT INTO prakiraan_cuaca (
            adm4, desa, cuaca, suhu_min, suhu_max, wind_speed, arah_angin, waktu_pembaruan, sumber, tcc
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $row['adm4'] ?? null,
            $row['desa'] ?? null,
            $row['cuaca'] ?? null,
            $row['suhu_min'] ?? null,
            $row['suhu_max'] ?? null,
            $row['wind_speed'] ?? null,
            $row['arah_angin'] ?? null,
            $row['waktu_pembaruan'] ?? null,
            $row['sumber'] ?? null,
            $row['tcc'] ?? null
        ]);
    }

    public function getLatestByAdm4(string $adm4) {
        $stmt = $this->pdo->prepare("SELECT * FROM prakiraan_cuaca WHERE adm4 = ? ORDER BY waktu_pembaruan DESC LIMIT 1");
        $stmt->execute([$adm4]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
