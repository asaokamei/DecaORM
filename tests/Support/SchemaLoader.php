<?php

namespace WScore\DecaORM\Tests\Support;

class SchemaLoader
{
    private static function getType(): string
    {
        $type = getenv('DB_TYPE') ?: 'sqlite';
        return in_array($type, ['sqlite', 'mysql', 'postgresql'], true) ? $type : 'sqlite';
    }

    public static function getSchemaDir(): string
    {
        $base = __DIR__ . '/../Fixtures/Relations/Sql';
        return $base . '/' . self::getType();
    }

    /**
     * Load schema SQL for a table (e.g. 'users', 'projects').
     * File includes DROP TABLE IF EXISTS at the top, then CREATE TABLE.
     */
    public static function loadTable(string $name): string
    {
        $path = self::getSchemaDir() . '/' . $name . '.sql';
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException("Schema file not found: {$path}");
        }
        return $sql;
    }
}
