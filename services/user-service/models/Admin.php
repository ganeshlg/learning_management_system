<?php

class Admin
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByEmail($email)
    {
        $sql = "SELECT * FROM admins WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function verifyPassword($email, $password)
    {
        $admin = $this->findByEmail($email);
        if (!$admin) return false;
        return password_verify($password, $admin['password']);
    }

    public function create($email, $password, $role = 'trainer', $name = null)
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO admins (email, password, role, name) VALUES (:email, :password, :role, :name)";
        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute([
                'email' => $email,
                'password' => $hash,
                'role' => $role,
                'name' => $name
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function listByRole($role = 'trainer')
    {
        $sql = "SELECT id, email, name, role, last_logged_in FROM admins WHERE role = :role";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateByEmail($email, array $fields)
    {
        $allowed = ['name', 'password', 'role'];
        $sets = [];
        $params = ['email' => $email];

        if (isset($fields['password'])) {
            $fields['password'] = password_hash($fields['password'], PASSWORD_BCRYPT);
        }

        foreach ($fields as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            $sets[] = "`$k` = :$k";
            $params[$k] = $v;
        }

        if (empty($sets)) return false;

        $sql = 'UPDATE admins SET ' . implode(', ', $sets) . ' WHERE email = :email';
        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function deleteByEmail($email)
    {
        $sql = "DELETE FROM admins WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute(['email' => $email]);
        } catch (PDOException $e) {
            return false;
        }
    }
}
