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
}