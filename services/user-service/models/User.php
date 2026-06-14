<?php

class User
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create($name, $email, $password)
    {
        $hashedPassword = password_hash(
            $password,
            PASSWORD_BCRYPT
        );

        $sql = "INSERT INTO users(name,email,password)
                VALUES(:name,:email,:password)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword
        ]);
    }

    public function findByEmail($email)
    {
        $sql = "SELECT * FROM users WHERE email=:email";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'email' => $email
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}