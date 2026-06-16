<?php

namespace WScore\DecaORM\Trait;

use WScore\DecaORM\Contracts\EntityInterface;

trait HydratorTrait
{
    abstract public function getPrimaryKeyColumn(): string;
    abstract public function listProperties(): array;
    abstract public function getColumnNameForProperty(string $propertyName): ?string;
    abstract public function getEntityClass(): string;

    /**
     * Maps a DB row onto a new entity instance. Does not use {@see \WScore\DecaORM\EntityCache};
     * identity mapping is handled by repositories ({@see RepositoryTrait::hydrateManagedFromRow}).
     */
    protected function materializeFromRow(array $data): EntityInterface
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
    public function applyRowData(EntityInterface $entity, array $data): void
    {
        $this->applyRowDataToEntity($entity, $data);
    }

    /**
     * Maps DB column values onto an entity (column names in $data).
     */
    protected function applyRowDataToEntity(EntityInterface $entity, array $data): void
    {
        foreach ($this->listProperties() as $propertyName) {
            $columnName = $this->getColumnNameForProperty($propertyName);
            if (array_key_exists($columnName, $data)) {
                $value = $data[$columnName];
                $entity->setRaw($propertyName, $value !== null ? (string) $value : null);
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