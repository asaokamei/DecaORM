<?php

namespace WScore\DecaORM\Contracts;

use WScore\DecaORM\Attribute;

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
     * Build a new entity from a DB row (no identity map). For cached / single-instance-per-id hydration,
     * use the repository {@see \WScore\DecaORM\Trait\RepositoryTrait::fetch()} path, which applies
     * {@see \WScore\DecaORM\EntityCache} and ORM context.
     */
    public function hydrate(array $data): EntityInterface;

    /**
     * Applies column values from a DB row onto an existing entity instance.
     *
     * @param array<string, mixed> $data
     */
    public function applyRowData(EntityInterface $entity, array $data): void;

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
     * @return array<>
     */
    public function getRelations(): array;

    /**
     * Get relation information for a specific property
     * 
     * @param string $propertyName The property name
     * @return mixed|null|Attribute\HasMany|Attribute\HasOne|Attribute\BelongsTo|Attribute\BelongsToOne|Attribute\MorphTo|Attribute\MorphToOne
     */
    public function getRelation(string $propertyName): mixed;
}