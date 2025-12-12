<?php
// config/database.php

// Matikan error warning agar output JSON bersih
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

class Database {
    // 1. URL Firebase kamu (sudah saya sesuaikan)
    private $projectUrl = 'https://desaverse-8a6cb-default-rtdb.asia-southeast1.firebasedatabase.app';
    
    // 2. KODE RAHASIA (WAJIB DIGANTI)
    // Ambil ini di Firebase Console > Project Settings > Service Accounts > Database Secrets
    private $dbSecret = 'PN2NP7zgvewT2sPgxCytgqAzUGTmlnhWTCbzhVJb'; 

    // Fungsi agar kompatibel dengan AuthController
    public function getConnection() {
        return $this;
    }

    // Fungsi inti komunikasi ke Firebase
    public function request($path, $method = 'GET', $data = null) {
        // Bersihkan path
        if(substr($path, 0, 1) !== '/') $path = '/' . $path;
        
        // Buat URL lengkap dengan auth secret
        $url = $this->projectUrl . $path . '.json?auth=' . $this->dbSecret;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass SSL agar tidak error di localhost
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        //curl_close($ch);

        if ($error) {
            return ["error" => $error];
        }

        return json_decode($response, true);
    }
}
?>