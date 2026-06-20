<?php

class Database
{
    private $driver;
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;

    public function __construct()
    {
        $this->driver = strtolower(getenv('DB_DRIVER') ?: 'mysql');
        $this->host = getenv('DB_HOST') ?: 'db';
        $this->port = getenv('DB_PORT') ?: ($this->driver === 'pgsql' ? '5432' : '3306');
        $this->db_name = getenv('DB_DATABASE') ?: 'lms';
        $this->username = getenv('DB_USERNAME') !== false ? getenv('DB_USERNAME') : ($this->driver === 'pgsql' ? 'postgres' : 'lmsuser');
        $this->password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($this->driver === 'pgsql' ? 'postgres' : 'lmspass');
    }

    public function connect()
    {
        try {
            if ($this->driver === 'pgsql' || $this->driver === 'postgres') {
                $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name}";
            } else {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            }

            $pdo = new PDO($dsn, $this->username, $this->password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }
}