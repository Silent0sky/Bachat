<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c   = config('db');
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $c['host'] ?? 'localhost',
                $c['name'] ?? 'bachat',
                $c['charset'] ?? 'utf8mb4'
            );
            try {
                self::$pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false, // true prepared statements
                ]);
            } catch (PDOException $e) {
                error_log('[Bachat DB] ' . $e->getMessage());
                if (config('app.env') === 'development') {
                    throw $e;
                }
                http_response_code(500);
                include APP . '/views/errors/500.php';
                exit;
            }
        }
        return self::$pdo;
    }

    /** Run a prepared statement and return it. */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        return self::run($sql, $params)->fetchColumn();
    }
}
