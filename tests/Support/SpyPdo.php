<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Support;

use PDO;
use PDOStatement;

final class SpyPdo
{
    /**
     * @var list<string>
     */
    public array $preparedSql = [];

    public function __construct(private PDO $inner)
    {
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->inner->setAttribute($attribute, $value);
    }

    public function exec(string $statement): int|false
    {
        return $this->inner->exec($statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedSql[] = $query;
        return $this->inner->prepare($query, $options);
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->inner->lastInsertId($name);
    }

    public function getInnerPdo(): PDO
    {
        return $this->inner;
    }
}