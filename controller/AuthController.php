<?php
// AuthController.php

include_once 'config/database.php';
include_once 'models/User.php';

class AuthController 
{
    private $db;
    private $user;

    public function __construct()
    {
        // 1. Koneksi Database Manual
        $database = new Database();
        $this->db = $database->getConnection(); // Return $this (objek Database itu sendiri)
        
        // 2. Load Model
        $this->user = new User($this->db);
    }

    public function register($data)
    {
        // Validasi Input
        if (!empty($data->name) && !empty($data->email) && !empty($data->password)) {
            
            $this->user->name = $data->name;
            $this->user->email = $data->email;
            $this->user->password = password_hash($data->password, PASSWORD_BCRYPT);

            // Cek dulu apakah email sudah ada?
            if ($this->user->emailExists()) {
                http_response_code(400);
                echo json_encode(['message' => 'Email sudah terdaftar.']);
            } else {
                // Jika belum ada, buat baru
                if ($this->user->create()) {
                    http_response_code(201);
                    echo json_encode(['message' => 'User berhasil dibuat.', 'id' => $this->user->id]);
                } else {
                    http_response_code(503);
                    echo json_encode(['message' => 'Gagal koneksi ke Firebase.']);
                }
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Data tidak lengkap.']);
        }
    }

    public function login($data)
    {
        if(!empty($data->email) && !empty($data->password)) {
            $this->user->email = $data->email;

            // 1. Cek Email di Firebase
            if ($this->user->emailExists()) {
                
                // 2. Verifikasi Password
                if (password_verify($data->password, $this->user->password)) {
                    
                    // 3. Cek Status Akun
                    if ($this->user->status == 1) {
                        http_response_code(200);
                        echo json_encode([
                            'message' => 'Login Berhasil.',
                            'user' => [
                                'id' => $this->user->id,
                                'name' => $this->user->name,
                                'email' => $this->user->email,
                            ],
                            'token' => 'desaverse-admin' 
                        ]);
                    } else {
                        http_response_code(403);
                        echo json_encode(['message' => 'Akun dinonaktifkan.']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(['message' => 'Password salah.']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Email tidak ditemukan.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Email dan password wajib diisi.']);
        }
    }
}
?>