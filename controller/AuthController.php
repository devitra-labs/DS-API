<?php

include_once 'config/database.php';
include_once 'models/User.php';

class AuthController 
{
    private $db;
    private $user;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    public function register($data)
    {
        // Validation input
        if (!empty($data->name) && !empty($data->email) && !empty($data->password)) {
            $this->user->name = $data->name;
            $this->user->email = $data->email;
            $this->user->password = password_hash($data->password, PASSWORD_BCRYPT);

            if ($this->user->create()) {
                http_response_code(201);
                echo json_encode(['message' => 'User berhasil dibuat.']);
            } else {
                http_response_code(503);
                echo json_encode(['message' => 'Gagal membuat user. Silahkan coba lagi.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Data tidak lengkap.']);
        }
    }

    public function login($data)
    {
        $this->user->email = $data->email;

        // 1. Check apakah email ada di DB
        if ($this->user->emailExists()) {
            // 2. Verifikasi password (Input User vs Hash PW)
            if (password_verify($data->password, $this->user->password)) {
                
                // 3. Check 'status'
                if ($this->user->status == 1) {
                    http_response_code(200);
                    echo json_encode([
                        'message' => 'Login Berhasil.',
                        'user' => [
                            'id' => $this->user->id,
                            'name' => $this->user->name,
                            'email' => $this->user->email,
                        ],
                        // Token JWT
                        'token' => 'token_api_desaverse',
                    ]);
                } else {
                    http_response_code(403);
                    echo json_encode(['message' => 'Akun anda dinonaktifkan.']);
                }
            } else {
                http_response_code(401);
                echo json_encode(['message' => 'Password salah.']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Email tidak ditemukan']);
        }
    }
}
