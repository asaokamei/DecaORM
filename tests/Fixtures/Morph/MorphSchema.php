<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Fixtures\Morph;

/**
 * Schema SQL paths for morph relation tests (mirrors Relations\SchemaLoader layout).
 */
final class MorphSchema
{
    private static function getType(): string
    {
        $type = getenv('DB_TYPE') ?: 'sqlite';
        return in_array($type, ['sqlite', 'mysql', 'postgresql'], true) ? $type : 'sqlite';
    }

    public static function getSchemaDir(): string
    {
        return __DIR__ . '/Sql/' . self::getType();
    }

    public static function loadTable(string $name): string
    {
        $path = self::getSchemaDir() . '/' . $name . '.sql';
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException("Morph schema file not found: {$path}");
        }
        return $sql;
    }
}
