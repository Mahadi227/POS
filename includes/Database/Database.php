<?php
// includes/Database/Database.php

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->conn = $this->connect();
    }

    private function connect(): PDO
    {
        require_once __DIR__ . '/../Config/config.php';

        $hosts = array_values(array_unique(array_filter([
            defined('DB_HOST') ? DB_HOST : 'localhost',
            '127.0.0.1',
            'localhost',
        ])));

        $lastError = null;
        foreach ($hosts as $host) {
            try {
                $dsn = 'mysql:host=' . $host . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $conn = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => true,
                ]);
                return $conn;
            } catch (PDOException $e) {
                $lastError = $e;
            }
        }

        $message = 'Database connection failed';
        if (defined('APP_DEBUG') && APP_DEBUG && $lastError) {
            $message .= ': ' . $lastError->getMessage();
        }

        if (php_sapi_name() !== 'cli') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
                http_response_code(503);
            }
            die(json_encode([
                'status'  => 'error',
                'message' => $message,
            ]));
        }

        throw $lastError ?? new PDOException($message);
    }

    private function reconnectIfNeeded(): void
    {
        if ($this->conn === null) {
            $this->conn = $this->connect();
            return;
        }

        try {
            $this->conn->query('SELECT 1');
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '2006') || str_contains($msg, '2013') || str_contains($msg, 'gone away')) {
                $this->conn = $this->connect();
            } else {
                throw $e;
            }
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        $this->reconnectIfNeeded();
        return $this->conn;
    }
}
