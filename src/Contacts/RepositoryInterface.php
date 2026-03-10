<?php

namespace WScore\DecaORM\Contacts;

use PDO;
use PDOStatement;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Collection;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Sql\Delete;
use WScore\DecaORM\Sql\Insert;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Sql\Update;

/**
 * @template T of EntityInterface
 */
interface RepositoryInterface
{
    /**
     * @return PDO
     */
    public function getDb(): PDO;

    public function sqlQuery(): Query;

    public function sqlInsert(array $data): Insert;

    public function sqlDelete(int|string|null $id = null): Delete;

    public function sqlUpdate(int|string|null $id = null, array $data = []): Update;

    /**
     * executes the SQL with data and returns results as PDOStatement.
     *
     * @param string $sql
     * @param array $data
     * @return false|PDOStatement
     */
    public function execute(string $sql, array $data): bool|PDOStatement;

    /**
     * executes the SQL with data and returns results as entities.
     * 
     * @param string $sql
     * @param array $data
     * @return EntityInterface[]
     */
    public function fetch(string $sql, array $data = []): array;

    /**
     * finds entities for a simple condition.
     *
     * @param int|string $id
     * @param string|null $column
     * @param string|null $orderBy
     * @return EntityInterface[]
     */
    public function find(int|string $id, ?string $column = null, ?string $orderBy = null): array;

    /**
     * Gets the repository for the given entity.
     */
    public function getRepository(string|EntityInterface $entity): ?RepositoryInterface;

    /**
     * Gets the hydrator for the given entity.
     */
    public function getHydrator(): ?HydratorInterface;

    /**
     * Gets the relation for the given property name.
     * @return HasMany|HasOne|BelongsTo|BelongsToOne|ManyToMany|null
     */
    public function getRelation(string $propertyName): mixed;

    /**
     * Loads the specified relation for the given entity or entities.
     * 
     * @param EntityInterface|array<EntityInterface> $entities The entity or entities for which the relation is to be loaded.
     * @param string $relationName The name of the relation property to load.
     * @return Collection|EntityCollection The loaded relation entities as a collection.
     *         Returns EntityCollection if the result contains EntityInterface instances, Collection otherwise.
     */
    public function load(EntityInterface|array $entities, string $relationName): Collection|EntityCollection;

    /**
     * Finds a single entity by ID.
     * 
     * @param int|string $id
     * @return EntityInterface|null
     */
    public function findById(int|string $id): ?EntityInterface;

    /**
     * Creates a new entity from data (does not save to a database).
     * 
     * Note: For more flexible entity creation (e.g., with additional parameters),
     * implement a custom method in your repository class (e.g., PostRepository::create).
     * 
     * @param array $data
     * @return EntityInterface
     */
    public function createEntity(array $data): EntityInterface;

    /**
     * Saves an entity (insert or update).
     * 
     * This is a convenience method that calls insertEntity() or updateEntity() based on the entity state.
     * 
     * @param EntityInterface $entity
     * @return void
     */
    public function save(EntityInterface $entity): void;

    /**
     * Deletes an entity using the repository's default deletion policy.
     *
     * @param EntityInterface $entity
     * @return void
     */
    public function delete(EntityInterface $entity): void;
}