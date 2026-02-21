<?php
class Database
{
    private $host = "localhost";
    private $db_name = "security_db";
    private $username = "root";
    private $password = "";

    private $conn;
    private static $instance = null;

    private function __construct()
    {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
        if ($this->conn) {
            $this->conn->set_charset("utf8mb4");
        }
    }

    public static function getConnection()
    {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance->conn;
    }
}
