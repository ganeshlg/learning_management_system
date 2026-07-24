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

        if (!is_array($data)) {
            $data = [];
        }

        if (
            empty($data['email']) ||
            empty($data['password']) ||
            (empty($data['name']) && empty($data['full_name']))
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
            $data['name'] ?? $data['full_name'] ?? null,
            $data['email'],
            $data['password'],
            $data
        );

        http_response_code(200);
        echo json_encode([
            "message" => "User registered"
        ]);
    }

    public function listUsers()
    {
        $users = $this->user->getAll();

        foreach ($users as &$user) {
            unset($user['password']);
        }

        http_response_code(200);
        echo json_encode([
            'users' => $users
        ]);
    }

    public function updateUser($id)
    {
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!is_array($data)) {
            $data = [];
        }

        if (empty($id) || empty($data)) {
            http_response_code(400);
            echo json_encode([
                'message' => 'user id and update data required'
            ]);
            return;
        }

        $existingUser = $this->user->findById($id);
        if (!$existingUser) {
            http_response_code(404);
            echo json_encode([
                'message' => 'User not found'
            ]);
            return;
        }

        $updated = $this->user->updateById($id, $data);
        if (!$updated) {
            http_response_code(409);
            echo json_encode([
                'message' => 'Unable to update user or email already exists'
            ]);
            return;
        }

        $updatedUser = $this->user->findById($id);
        unset($updatedUser['password']);

        http_response_code(200);
        echo json_encode([
            'message' => 'User updated successfully',
            'user' => $updatedUser
        ]);
    }

    public function deleteUser($id)
    {
        if (empty($id)) {
            http_response_code(400);
            echo json_encode([
                'message' => 'user id required'
            ]);
            return;
        }

        $existingUser = $this->user->findById($id);
        if (!$existingUser) {
            http_response_code(404);
            echo json_encode([
                'message' => 'User not found'
            ]);
            return;
        }

        $deleted = $this->user->deleteById($id);
        if (!$deleted) {
            http_response_code(409);
            echo json_encode([
                'message' => 'Unable to delete user'
            ]);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'message' => 'User deleted successfully'
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