<?php

require_once __DIR__ . '/../models/User.php';

class AuthController
{
    private User $user;

    public function __construct(PDO $db)
    {
        $this->user = new User($db);
    }

    private function sendRegistrationMail($toEmail, $userName = null)
    {
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $displayName = $userName ?: $toEmail;
        $fromEmail = getenv('MAIL_FROM') ?: 'no-reply@lms.local';
        $fromName = getenv('MAIL_FROM_NAME') ?: 'LMS';

        $subject = 'Welcome to LMS';
        $message = "Hello {$displayName},\n\n";
        $message .= "Your account has been registered successfully.\n";
        $message .= "This is a test email for registration confirmation.\n\n";
        $message .= "Thank you,\n{$fromName}";

        $headers = [];
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        $smtpHost = 'smtp.gmail.com';
        $smtpPort = '587';
        $smtpUsername = 'ganeshofficial2108@gmail.com';
        $smtpPassword = getenv('SMTP_PASSWORD') ?: '';
        $smtpEncryption = 'tls';

        if ($smtpHost && $smtpUsername && $smtpPassword) {
            $socket = @stream_socket_client(($smtpEncryption === 'ssl' ? 'ssl://' : '') . $smtpHost . ':' . $smtpPort, $errno, $errstr, 15);
            if ($socket) {
                stream_set_timeout($socket, 15);
                $this->readSmtpResponse($socket, '220');

                fwrite($socket, "EHLO localhost\r\n");
                $this->readSmtpResponse($socket, '250');

                if ($smtpEncryption === 'tls') {
                    fwrite($socket, "STARTTLS\r\n");
                    $this->readSmtpResponse($socket, '220');
                    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    fwrite($socket, "EHLO localhost\r\n");
                    $this->readSmtpResponse($socket, '250');
                }

                fwrite($socket, "AUTH LOGIN\r\n");
                $this->readSmtpResponse($socket, '334');
                fwrite($socket, base64_encode($smtpUsername) . "\r\n");
                $this->readSmtpResponse($socket, '334');
                fwrite($socket, base64_encode($smtpPassword) . "\r\n");
                $this->readSmtpResponse($socket, '235');

                fwrite($socket, "MAIL FROM:<{$fromEmail}>\r\n");
                $this->readSmtpResponse($socket, '250');
                fwrite($socket, "RCPT TO:<{$toEmail}>\r\n");
                $this->readSmtpResponse($socket, '250');
                fwrite($socket, "DATA\r\n");
                $this->readSmtpResponse($socket, '354');

                $emailBody = "Subject: {$subject}\r\n";
                $emailBody .= implode("\r\n", $headers) . "\r\n";
                $emailBody .= "\r\n" . $message . "\r\n." . "\r\n";
                fwrite($socket, $emailBody);
                $this->readSmtpResponse($socket, '250');
                fwrite($socket, "QUIT\r\n");
                fclose($socket);

                return true;
            }

            error_log('Registration email failed via SMTP for ' . $toEmail . ': ' . $errstr);
            return false;
        }

        $sent = mail($toEmail, $subject, $message, implode("\r\n", $headers));
        if (!$sent) {
            error_log('Registration email failed for ' . $toEmail);
        }

        return $sent;
    }

    private function readSmtpResponse($socket, $expectedCode)
    {
        $response = '';
        while (true) {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new RuntimeException('SMTP connection lost');
            }
            $response .= $line;
            if (substr($line, 3, 1) === ' ' ) {
                break;
            }
        }

        if (strpos($response, $expectedCode) !== 0) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
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

        $created = $this->user->create(
            $data['name'] ?? $data['full_name'] ?? null,
            $data['email'],
            $data['password'],
            $data
        );

        if ($created) {
            $this->sendRegistrationMail($data['email'], $data['name'] ?? $data['full_name'] ?? null);
        }

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