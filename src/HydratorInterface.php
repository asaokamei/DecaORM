<?php

namespace WScore\DecaORM;

interface HydratorInterface
{
    /**
     * Whether the primary key is auto-generated
     */
    public function isPkAutoNumber(): bool;
    /**
     * Entity class name
     */
    public function getEntityClass(): string;

    /**
     * DB table name
     */
    public function getTableName(): string;
    /**
     * DB primary key
     */
    public function getPrimaryKey(): string;

    public function getPrimaryKeyColumn(): string;
    public function getCreatedAtColumn(): ?string;
    public function getUpdatedAtColumn(): ?string;

    /**
     * List of entity properties
     */
    public function listProperties(): array;

    /**
     * Column name for creation timestamp
     */
    public function getCreatedAt(): ?string;

    /**
     * Column name for update timestamp
     */
    public function getUpdatedAt(): ?string;

    /**
     * Convert an associative array (DB row) to an entity (hydration)
     */
    public function hydrate(array $data): EntityInterface;

    /**
     * Convert an entity to an associative array (dehydration)
     */
    public function dehydrate(EntityInterface $entity): array;

    /**
     * Get property name for a given column name
     * If column name mapping is not available, returns the column name as-is
     */
    public function getPropertyNameForColumn(string $columnName): ?string;

    /**
     * Get column name for a given property name
     * If property name mapping is not available, returns the property name as-is
     */
    public function getColumnNameForProperty(string $propertyName): ?string;
}