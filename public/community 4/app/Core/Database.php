<?php
declare(strict_types=1);

namespace Core;

use PDO;

class Database
{
    /** @var Database|null */
    private static $instance = null;

    /** @var PDO */
    private $connection;

    private function __construct()
    {
        $dbName = $_ENV['DB_NAME'] ?? 'studylink_db';
        $socket = $_ENV['DB_SOCKET'] ?? '';

        if (!empty($socket)) {
            $dsn = sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
                $socket,
                $dbName
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'] ?? 'localhost',
                $_ENV['DB_PORT'] ?? '3306',
                $dbName
            );
        }

        $this->connection = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? 'root', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
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
