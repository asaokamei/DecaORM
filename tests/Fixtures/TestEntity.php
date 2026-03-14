<?php

namespace WScore\DecaORM\Tests\Fixtures;

use WScore\DecaORM\Contracts\EntityInterface;

/**
 * Test stub implementing EntityInterface.
 * All instances are the same class (for EntityCollection same-class validation).
 * getRaw($name) returns arbitrary data passed in constructor.
 */
class TestEntity implements EntityInterface
{
    public static function getRepositoryClass(): string
    {
        return TestRepository::class;
    }

    public function __construct(
        private null|int|string $id,
        private array $data = []
    ) {
    }

    public function getId(): null|int|string
    {
        return $this->id;
    }

    public function getRaw(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function setRaw(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }
}
