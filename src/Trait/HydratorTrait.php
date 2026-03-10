<?php

namespace WScore\DecaORM\Trait;

use WScore\DecaORM\EntityCache;
use WScore\DecaORM\Contracts\EntityInterface;

trait HydratorTrait
{
    abstract public function getPrimaryKeyColumn(): string;
    abstract public function listProperties(): array;
    abstract public function getColumnNameForProperty(string $propertyName): ?string;
    abstract public function getEntityClass(): string;

    /**
     * Converts an associative array (DB row) to an entity (hydration)
     */
    protected function hydrateEntity(array $data): EntityInterface
    {
        if (!isset($data[$this->getPrimaryKeyColumn()])) {
            throw new \RuntimeException('Primary key is not set in the data.');
        }
        $class = $this->getEntityClass();
        $pKey = $data[$this->getPrimaryKeyColumn()];
        if (EntityCache::has($class, $pKey)) {
            $targetEntity = EntityCache::get($class, $pKey);
        } else {
            $targetEntity = new $class();
            EntityCache::set($class, $pKey, $targetEntity);
        }

        // Set data to the target entity (update with latest data even if cached)
        // Data comes from DB with column names, need to map to property names
        foreach ($this->listProperties() as $propertyName) {
            $columnName = $this->getColumnNameForProperty($propertyName);
            if (isset($data[$columnName])) {
                // set Column value as string; this is for compatibility with the EntityTrait
                $targetEntity->set($propertyName, (string) $data[$columnName]);
            }
        }
        return $targetEntity;
    }

    /**
     * Converts an entity to an associative array (dehydration)
     */
    public function dehydrateEntity(EntityInterface $entity): array
    {
        $data = [];
        // Get column names from listProperties() and map to property names for entity access
        foreach ($this->listProperties() as $propertyName) {
            $columnName = $this->getColumnNameForProperty($propertyName);
            $data[$columnName] = $entity->get($propertyName);
        }
        return $data;
    }
}