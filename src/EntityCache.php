<?php

namespace WScore\DecaORM;

use WScore\DecaORM\Contracts\EntityInterface;

/**
 * Class to manage entity caching
 */
class EntityCache
{
    /** @var EntityInterface[][] */
    private static array $cached = [];

    /**
     * Get an entity from the cache
     * 
     * @param string $class Entity class name
     * @param int|string $id Entity ID
     * @return EntityInterface|null
     */
    public static function get(string $class, int|string $id): ?EntityInterface
    {
        return self::$cached[$class][$id] ?? null;
    }

    /**
     * Save an entity to the cache
     * 
     * @param string $class Entity class name
     * @param int|string $id Entity ID
     * @param EntityInterface $entity Entity
     */
    public static function set(string $class, int|string $id, EntityInterface $entity): void
    {
        if (!isset(self::$cached[$class])) {
            self::$cached[$class] = [];
        }
        self::$cached[$class][$id] = $entity;
    }

    /**
     * Check if an entity exists in the cache
     * 
     * @param string $class Entity class name
     * @param int|string $id Entity ID
     * @return bool
     */
    public static function has(string $class, int|string $id): bool
    {
        return isset(self::$cached[$class][$id]);
    }

    /**
     * Register or get an entity in the cache
     * If the ID is null, return the original entity. If the ID is not null, return the cached entity if it exists, otherwise register it in the cache and return it.
     * 
     * @param EntityInterface $entity Entity
     * @return EntityInterface Cached entity or original entity
     */
    public static function cache(EntityInterface $entity): EntityInterface
    {
        $class = get_class($entity);
        $id = $entity->getId();
        if ($id === null) {
            return $entity;
        }
        if (self::has($class, $id)) {
            return self::get($class, $id);
        }
        self::set($class, $id, $entity);
        return $entity;
    }

    /**
     * Clear the cache
     * 
     * @param string|null $class Clear the cache for the specified class. If null, clear all.
     */
    public static function clear(?string $class = null): void
    {
        if ($class === null) {
            self::$cached = [];
        } else {
            unset(self::$cached[$class]);
        }
    }

    public static function count(?string $class = null): int
    {
        if (is_string($class)) {
            return isset(self::$cached[$class]) ? count(self::$cached[$class]) : 0;
        }
        $count = 0;
        foreach (self::$cached as $entities) {
            $count += count($entities);
        }
        return $count;
    }
}

