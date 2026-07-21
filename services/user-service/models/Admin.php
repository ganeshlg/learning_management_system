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

    public function create($email, $password, $role = 'trainer', $name = null, array $profileData = [])
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $columns = ['email', 'password', 'role', 'name'];
        $params = [
            'email' => $email,
            'password' => $hash,
            'role' => $role,
            'name' => $name
        ];
        $allowedFields = ['profile_description', 'experience_years', 'photo_url', 'expertise', 'phone', 'location', 'linkedin_url', 'website_url'];

        foreach ($profileData as $key => $value) {
            if (!in_array($key, $allowedFields, true)) {
                continue;
            }

            if ($key === 'experience_years' && $value !== null) {
                $value = (int) $value;
            }

            if ($key === 'expertise' && is_array($value)) {
                $value = implode(', ', array_filter(array_map('trim', $value)));
            }

            if ($key === 'expertise' && $value === '') {
                $value = null;
            }

            $columns[] = $key;
            $params[$key] = $value;
        }

        $placeholders = [];
        foreach ($columns as $column) {
            $placeholders[] = ':' . $column;
        }

        $sql = 'INSERT INTO admins (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function listByRole($role = 'trainer')
    {
        $sql = "SELECT id, email, name, role, last_logged_in, profile_description, experience_years, photo_url, expertise, phone, location, linkedin_url, website_url FROM admins WHERE role = :role";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateByEmail($email, array $fields)
{
    $allowed = [
        'name',
        'password',
        'role',
        'profile_description',
        'experience_years',
        'photo_url',
        'expertise',
        'phone',
        'location',
        'linkedin_url',
        'website_url'
    ];

    $sets = [];
    $params = ['email' => $email];

    if (isset($fields['password'])) {
        $fields['password'] = password_hash($fields['password'], PASSWORD_BCRYPT);
    }

    foreach ($fields as $k => $v) {
        if (!in_array($k, $allowed, true)) {
            continue;
        }

        $sets[] = "$k = :$k";
        $params[$k] = $v;
    }

    if (empty($sets)) {
        return false;
    }

    $sql = 'UPDATE admins SET ' . implode(', ', $sets) . ' WHERE email = :email';

    $stmt = $this->db->prepare($sql);

    try {
        return $stmt->execute($params);
    } catch (PDOException $e) {
        echo $e->getMessage();
        return false;
    }
}

    // public function updateByEmail($email, array $fields)
    // {
    //     $allowed = ['name', 'password', 'role', 'profile_description', 'experience_years', 'photo_url', 'expertise', 'phone', 'location', 'linkedin_url', 'website_url'];
    //     $sets = [];
    //     $params = ['email' => $email];

    //     if (isset($fields['password'])) {
    //         $fields['password'] = password_hash($fields['password'], PASSWORD_BCRYPT);
    //     }

    //     foreach ($fields as $k => $v) {
    //         if (!in_array($k, $allowed, true)) continue;
    //         $sets[] = "`$k` = :$k";
    //         $params[$k] = $v;
    //     }

    //     if (empty($sets)) return false;

    //     $sql = 'UPDATE admins SET ' . implode(', ', $sets) . ' WHERE email = :email';
    //     $stmt = $this->db->prepare($sql);
    //     try {
    //         return $stmt->execute($params);
    //     } catch (PDOException $e) {
    //         echo $e->getMessage();
    //         return false;
    //     }
    // }

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
