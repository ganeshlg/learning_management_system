<?php

class User
{
    private PDO $db;

    private const ENROLLMENT_FIELDS = [
        'full_name' => 'full_name',
        'fullName' => 'full_name',
        'passport_photo_url' => 'passport_photo_url',
        'passportPhotoUrl' => 'passport_photo_url',
        'date_of_birth' => 'date_of_birth',
        'dateOfBirth' => 'date_of_birth',
        'gender' => 'gender',
        'mobile_number' => 'mobile_number',
        'mobileNumber' => 'mobile_number',
        'address' => 'address',
        'city_state_pin' => 'city_state_pin',
        'cityStatePin' => 'city_state_pin',
        'emergency_contact' => 'emergency_contact',
        'emergencyContact' => 'emergency_contact',
        'educational_qualification' => 'educational_qualification',
        'educationalQualification' => 'educational_qualification',
        'college_university' => 'college_university',
        'collegeUniversity' => 'college_university',
        'year_of_graduation' => 'year_of_graduation',
        'yearOfGraduation' => 'year_of_graduation',
        'current_status' => 'current_status',
        'currentStatus' => 'current_status',
        'current_organization' => 'current_organization',
        'currentOrganization' => 'current_organization',
        'total_experience' => 'total_experience',
        'totalExperience' => 'total_experience',
        'business_name' => 'business_name',
        'businessName' => 'business_name',
        'areas_of_interest' => 'areas_of_interest',
        'areasOfInterest' => 'areas_of_interest',
        'why_join_program' => 'why_join_program',
        'whyJoinProgram' => 'why_join_program',
        'business_idea' => 'business_idea',
        'businessIdea' => 'business_idea',
        'skills_to_develop' => 'skills_to_develop',
        'skillsToDevelop' => 'skills_to_develop',
        'how_heard_about_program' => 'how_heard_about_program',
        'howHeardAboutProgram' => 'how_heard_about_program',
        'documents_enclosed' => 'documents_enclosed',
        'documentsEnclosed' => 'documents_enclosed',
        'declaration' => 'declaration',
        'signature' => 'signature',
        'declaration_date' => 'declaration_date',
        'declarationDate' => 'declaration_date',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    private function normalizeEnrollmentData(array $data): array
    {
        $normalized = [];

        foreach (self::ENROLLMENT_FIELDS as $inputKey => $column) {
            if (!array_key_exists($inputKey, $data)) {
                continue;
            }

            $value = $data[$inputKey];

            if (is_array($value)) {
                $value = json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'yes' : 'no';
            } elseif ($value === '') {
                $value = null;
            }

            $normalized[$column] = $value;
        }

        return $normalized;
    }

    public function create($name, $email, $password, array $profileData = [])
    {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $name = trim((string) ($name ?? ''));
        $profileData = $this->normalizeEnrollmentData($profileData);

        if ($name === '' && isset($profileData['full_name'])) {
            $name = trim((string) $profileData['full_name']);
        }

        if ($name === '') {
            $name = 'User';
        }

        $columns = ['name', 'email', 'password'];
        $placeholders = [':name', ':email', ':password'];
        $params = [
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
        ];

        foreach ($profileData as $column => $value) {
            $columns[] = $column;
            $placeholders[] = ':' . $column;
            $params[$column] = $value;
        }

        $sql = 'INSERT INTO users(' . implode(', ', $columns) . ') VALUES(' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function syncEnrollmentData($userId, array $data): bool
    {
        $fields = [];
        $params = ['id' => $userId];

        if (array_key_exists('name', $data) && trim((string) $data['name']) !== '') {
            $fields[] = 'name = :name';
            $params['name'] = trim((string) $data['name']);
        } elseif (array_key_exists('full_name', $data) && trim((string) $data['full_name']) !== '') {
            $fields[] = 'name = :name';
            $params['name'] = trim((string) $data['full_name']);
        }

        foreach ($this->normalizeEnrollmentData($data) as $column => $value) {
            $fields[] = $column . ' = :' . $column;
            $params[$column] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function getAll(): array
    {
        $sql = 'SELECT id, name, email, full_name, passport_photo_url, date_of_birth, gender, mobile_number, address, city_state_pin, emergency_contact, educational_qualification, college_university, year_of_graduation, current_status, current_organization, total_experience, business_name, areas_of_interest, why_join_program, business_idea, skills_to_develop, how_heard_about_program, documents_enclosed, declaration, signature, declaration_date FROM users ORDER BY id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $sql = 'SELECT * FROM users WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateById($id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('name', $data) && trim((string) $data['name']) !== '') {
            $fields[] = 'name = :name';
            $params['name'] = trim((string) $data['name']);
        }

        if (array_key_exists('email', $data) && trim((string) $data['email']) !== '') {
            $email = trim((string) $data['email']);
            $existing = $this->findByEmail($email);
            if ($existing && (int) $existing['id'] !== (int) $id) {
                return false;
            }

            $fields[] = 'email = :email';
            $params['email'] = $email;
        }

        if (array_key_exists('password', $data) && trim((string) $data['password']) !== '') {
            $fields[] = 'password = :password';
            $params['password'] = password_hash(trim((string) $data['password']), PASSWORD_BCRYPT);
        }

        if (!array_key_exists('name', $data) && (array_key_exists('full_name', $data) || array_key_exists('fullName', $data))) {
            $fullNameValue = $data['full_name'] ?? $data['fullName'] ?? null;
            if (trim((string) $fullNameValue) !== '') {
                $fields[] = 'name = :name';
                $params['name'] = trim((string) $fullNameValue);
            }
        }

        foreach ($this->normalizeEnrollmentData($data) as $column => $value) {
            $fields[] = $column . ' = :' . $column;
            $params[$column] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function deleteById($id): bool
    {
        $sql = 'DELETE FROM users WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    public function findByEmail($email)
    {
        $sql = 'SELECT * FROM users WHERE email=:email';

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'email' => $email
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}