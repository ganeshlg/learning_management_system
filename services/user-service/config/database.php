<?php

class Database
{
    private $host;
    private $db_name;
    private $username;
    private $password;

    public function __construct()
    {
        $this->host = getenv('DB_HOST') ?: 'db';
        $this->db_name = getenv('DB_DATABASE') ?: 'lms';
        $this->username = getenv('DB_USERNAME') ?: 'lmsuser';
        $this->password = getenv('DB_PASSWORD') ?: 'lmspass';
    }

    public function connect()
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $this->username, $this->password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }
}