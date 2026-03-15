<?php
namespace WScore\DecaORM\Tests\Support;

use PDO;

class DbConnection 
{
    public static function get(): PDO
    {
        $type = getenv('DB_TYPE') ?: 'sqlite';
        $pdo = match ($type) {
            'sqlite' => new PDO('sqlite::memory:'),
            'mysql' => new PDO('mysql:host=127.0.0.1;dbname=test_db', 'root', ''),
            'postgresql' => new PDO('pgsql:host=127.0.0.1;dbname=test_db', 'postgres', ''),
            default => new PDO('sqlite::memory:'),
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}