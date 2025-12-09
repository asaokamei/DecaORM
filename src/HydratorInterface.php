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
     * Name of the identifier property.
     */
    public function getPrimaryKey(): string;

    /**
     * Name of the DB's primary key column.
     */
    public function getPrimaryKeyColumn(): string;

    /**
     * Name of the DB's column name for the createdAt.
     * Returns null if not present.
     */
    public function getCreatedAtColumn(): ?string;

    /**
     * Name of the DB's column name for the updatedAt.
     * * returns null if not present.
     */
    public function getUpdatedAtColumn(): ?string;

    /**
     * List of entity properties
     */
    public function listProperties(): array;

    /**
     * Property name for creation timestamp
     */
    public function getCreatedAt(): ?string;

    /**
     * Property name for update timestamp
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
     * Get a property name for a given column name
     * If column name mapping is not available, returns the column name as-is
     */
    public function getPropertyNameForColumn(string $columnName): ?string;

    /**
     * Get column name for a given property name
     * If property name mapping is not available, returns the property name as-is
     */
    public function getColumnNameForProperty(string $propertyName): ?string;

    /**
     * Get all relations for this entity
     * 
     * @return array<string, array{type: string, targetEntity: string, ...}>
     */
    public function getRelations(): array;

    /**
     * Get relation information for a specific property
     * 
     * @param string $propertyName The property name
     * @return array{type: string, targetEntity: string, ...}|null
     */
    public function getRelation(string $propertyName): mixed;
}