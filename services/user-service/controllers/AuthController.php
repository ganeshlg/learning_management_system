<?php

require_once __DIR__ . '/../models/User.php';

class AuthController
{
    private User $user;

    public function __construct(PDO $db)
    {
        $this->user = new User($db);
    }

    public function register()
    {
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (
            empty($data['name']) ||
            empty($data['email']) ||
            empty($data['password'])
        ) {
            http_response_code(200);

            echo json_encode([
                "message" => "All fields required"
            ]);
            return;
        }

        $existing = $this->user->findByEmail(
            $data['email']
        );

        if ($existing) {
            http_response_code(200);

            echo json_encode([
                "message" => "Email already exists"
            ]);
            return;
        }

        $this->user->create(
            $data['name'],
            $data['email'],
            $data['password']
        );

        http_response_code(200);
        echo json_encode([
            "message" => "User registered"
        ]);
    }

    public function login()
    {
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        $user = $this->user->findByEmail(
            $data['email']
        );

        if (
            !$user ||
            !password_verify(
                $data['password'],
                $user['password']
            )
        ) {
            http_response_code(200);

            echo json_encode([
                "message" => "Invalid credentials"
            ]);
            return;
        }

        http_response_code(200);
        echo json_encode([
            "message" => "Login successful",
            "user" => [
                "id" => $user['id'],
                "name" => $user['name'],
                "email" => $user['email']
            ]
        ]);
    }
}