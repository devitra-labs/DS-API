<?php
// --- JURUS SAKTI PHP 8.2 ---
// Baris ini memaksa PHP menerima variabel apa saja tanpa error Deprecated
#[AllowDynamicProperties] 
class User {
    private $conn;
    private $table_name = "users";

    // Kamu boleh hapus public $id yang tadi, atau biarkan saja tidak masalah.
    // Dengan adanya #[AllowDynamicProperties] di atas, kita tidak wajib menulisnya.
    
    public $id; 
    public $name;
    public $email;
    public $password;
    public $status;
    public $token; 

    public function __construct($db) {
        $this->conn = $db;
    }

    // Fungsi Registrasi
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " SET name=:name, email=:email, password=:password, status=:status";
        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password = htmlspecialchars(strip_tags($this->password));
        $this->status = 1;

        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":status", $this->status);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Fungsi Cek Email untuk Login
    public function emailExists() {
        $query = "SELECT id, name, password, status FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $this->email = htmlspecialchars(strip_tags($this->email));
        $stmt->bindParam(1, $this->email);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Dengan #[AllowDynamicProperties], bagian ini tidak akan error lagi
            // Walaupun server "lupa" kalau ada public $id
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->password = $row['password']; 
            $this->status = $row['status'];

            return true;
        }
        return false;
    }
}
?>