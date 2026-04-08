<?php

namespace WScore\DecaORM;

use WScore\DecaORM\Contracts\EntityInterface;

/**
 * Identity map (per {@see OrmManager} instance).
 */
class EntityCache
{
    /** @var array<string, array<int|string, EntityInterface>> */
    private array $cached = [];

    public function get(string $class, int|string $id): ?EntityInterface
    {
        return $this->cached[$class][$id] ?? null;
    }

    public function set(string $class, int|string $id, EntityInterface $entity): void
    {
        if (!isset($this->cached[$class])) {
            $this->cached[$class] = [];
        }
        $this->cached[$class][$id] = $entity;
    }

    public function has(string $class, int|string $id): bool
    {
        return isset($this->cached[$class][$id]);
    }

    public function cache(EntityInterface $entity): EntityInterface
    {
        $class = get_class($entity);
        $id = $entity->getId();
        if ($id === null) {
            return $entity;
        }
        if ($this->has($class, $id)) {
            return $this->get($class, $id);
        }
        $this->set($class, $id, $entity);
        return $entity;
    }

    public function clear(?string $class = null): void
    {
        if ($class === null) {
            $this->cached = [];
        } else {
            unset($this->cached[$class]);
        }
    }

    public function count(?string $class = null): int
    {
        if (is_string($class)) {
            return isset($this->cached[$class]) ? count($this->cached[$class]) : 0;
        }
        $count = 0;
        foreach ($this->cached as $entities) {
            $count += count($entities);
        }
        return $count;
    }
}
