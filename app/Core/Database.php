<?php
declare(strict_types=1);

namespace Core;

use PDO;

class Database
{
    private static ?self $instance = null;
    private PDO $connection;

    private function __construct()
    {
        $host    = $_ENV['DB_HOST']    ?? '127.0.0.1';
        $port    = $_ENV['DB_PORT']    ?? '3306';
        $name    = $_ENV['DB_NAME']    ?? 'connect_academia1';
        $user    = $_ENV['DB_USER']    ?? 'root';
        $pass    = $_ENV['DB_PASS']    ?? 'root';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        $socket  = $_ENV['DB_SOCKET']  ?? '';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Essaye le socket Unix si disponible, sinon bascule sur TCP
        if (!empty($socket) && file_exists($socket)) {
            try {
                $dsn = "mysql:unix_socket={$socket};dbname={$name};charset={$charset}";
                $this->connection = new PDO($dsn, $user, $pass, $options);
                return;
            } catch (\PDOException $e) {
                // Socket existant mais connexion échouée → fallback TCP
            }
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $this->connection = new PDO($dsn, $user, $pass, $options);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
