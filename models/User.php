<?php
// models/User.php

#[AllowDynamicProperties] 
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $name;
    public $email;
    public $password;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    // CREATE USER
    public function create() {
        // Sanitasi
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        // Password harus sudah di-hash dari controller, tapi kita amanin lagi
        // $this->password = ... (sudah di hash di controller)
        $this->status = 1;

        // LOGIKA KUNCI: Gunakan MD5 Email sebagai ID
        // Ini pengganti "Auto Increment" agar kita bisa cari user by email dengan cepat
        $userId = md5(strtolower($this->email));
        $this->id = $userId;

        $data = [
            'id'      => $this->id,
            'name'    => $this->name,
            'email'   => $this->email,
            'password'=> $this->password,
            'status'  => $this->status,
            'created' => date('Y-m-d H:i:s')
        ];

        // Kirim ke Firebase dengan method PUT (Simpan di laci khusus ID ini)
        $path = $this->table_name . '/' . $userId;
        $result = $this->conn->request($path, 'PUT', $data);

        // Jika tidak ada error curl dan data tersimpan
        if ($result && !isset($result['error'])) {
            return true;
        }
        return false;
    }

    // CEK EMAIL / LOGIN
    public function emailExists() {
        // Cari laci berdasarkan MD5 Email
        $checkId = md5(strtolower($this->email));
        $path = $this->table_name . '/' . $checkId;
        
        // Ambil data (GET)
        $user = $this->conn->request($path, 'GET');

        // Validasi: Firebase mengembalikan null jika data tidak ada
        if ($user && !isset($user['error'])) {
            $this->id = $user['id'];
            $this->name = $user['name'];
            $this->password = $user['password'];
            $this->status = $user['status'];
            return true;
        }
        return false;
    }
}
?>