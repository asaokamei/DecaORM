<?php

namespace WScore\DecaORM\Contracts;

use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\OrmManager;

interface EntityInterface
{
    /**
     * returns a class name for the repository class for the entity.
     */
    public static function getRepositoryClass(): string;

    /**
     * Retrieves the unique identifier for the entity.
     */
    public function getId(): null|int|string;

    /**
     * Retrieves the property value of the name.
     */
    public function getRaw(string $name): mixed;

    /**
     * Sets the property value of the name.
     */
    public function setRaw(string $name, mixed $value): void;

    /**
     * Associates a relation by property name (in-memory; FK / inverse handling per relation type).
     */
    public function associate(string $relationName, EntityInterface|iterable|EntityCollection|null $targetOrTargets): void;

    /**
     * Optional ORM context (repositories set this after hydrate / createEntity).
     */
    public function setOrm(?OrmManager $orm): void;

    public function getOrm(): ?OrmManager;
}
