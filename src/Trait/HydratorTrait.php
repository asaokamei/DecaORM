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

        $this->applyRowDataToEntity($targetEntity, $data);
        return $targetEntity;
    }

    /**
     * Hydrates a new entity from a row without {@see EntityCache} (for streaming / large reads).
     */
    protected function hydrateEntityDetached(array $data): EntityInterface
    {
        if (!isset($data[$this->getPrimaryKeyColumn()])) {
            throw new \RuntimeException('Primary key is not set in the data.');
        }
        $class = $this->getEntityClass();
        $targetEntity = new $class();
        $this->applyRowDataToEntity($targetEntity, $data);
        return $targetEntity;
    }

    /**
     * Maps DB column values onto an entity (column names in $data).
     */
    protected function applyRowDataToEntity(EntityInterface $entity, array $data): void
    {
        foreach ($this->listProperties() as $propertyName) {
            $columnName = $this->getColumnNameForProperty($propertyName);
            if (isset($data[$columnName])) {
                $entity->setRaw($propertyName, (string) $data[$columnName]);
            }
        }
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
            $data[$columnName] = $entity->getRaw($propertyName);
        }
        return $data;
    }
}