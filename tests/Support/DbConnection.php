<?php

namespace WScore\DecaORM\Tests\Support;

use PDO;

/**
 * Shared PDO for the whole test process (per {@see getenv('DB_TYPE')}).
 *
 * Opening a new connection per test exhausts PostgreSQL {@code max_connections} in CI; tests that
 * need a clean state should run {@code DROP} / schema loaders in {@see \PHPUnit\Framework\TestCase::setUp()}.
 */
class DbConnection
{
    private static ?PDO $pdo = null;

    private static ?string $pdoKey = null;

    public static function get(): PDO
    {
        $type = getenv('DB_TYPE') ?: 'sqlite';
        if (self::$pdo !== null && self::$pdoKey === $type) {
            return self::$pdo;
        }

        $pdo = match ($type) {
            'sqlite' => new PDO('sqlite::memory:'),
            'mysql' => new PDO('mysql:host=127.0.0.1;dbname=test_db', 'root', ''),
            'postgresql' => new PDO('pgsql:host=127.0.0.1;dbname=test_db', 'postgres', ''),
            default => new PDO('sqlite::memory:'),
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo = $pdo;
        self::$pdoKey = $type;

        return self::$pdo;
    }
}