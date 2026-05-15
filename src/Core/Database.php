<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;
    private string $driver;

    private function __construct()
    {
        $this->driver = env('DB_DRIVER', 'pgsql');

        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', $this->driver === 'mysql' ? '3306' : '5432');
        $name = env('DB_NAME', 'postgres');
        $user = env('DB_USER', 'postgres');
        $pass = env('DB_PASS', '');

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if ($this->driver === 'mysql') {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            // ANSI_QUOTES lets us use double-quoted identifiers in both MySQL and PostgreSQL
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] =
                "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode = CONCAT(@@sql_mode, ',ANSI_QUOTES')";
        } else {
            $sslmode = env('DB_SSLMODE', 'require');
            $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";
        }

        try {
            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            if (env('APP_DEBUG', 'false') === 'true') {
                throw $e;
            }
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(503);
            die('Service temporarily unavailable.');
        }
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

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId(string $sequence = ''): int
    {
        return (int) $this->connection->lastInsertId($sequence ?: null);
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollback(): void
    {
        $this->connection->rollBack();
    }

    private function __clone() {}
}
