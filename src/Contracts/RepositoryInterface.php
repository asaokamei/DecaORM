<?php

namespace WScore\DecaORM\Contracts;

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
     * ### Infrastructure
     */

    /**
     * Returns PDO used by this repository.
     */
    public function getDb(): PDO;

    /**
     * Returns the hydrator that defines mapping for this repository's entity.
     *
     * This should be available for all concrete repositories; nullable is for flexibility
     * (e.g., custom repositories that do not use AttributeHydrator).
     */
    public function getHydrator(): ?HydratorInterface;

    /**
     * Gets the repository for the given entity (or entity class).
     *
     * Typical use: relation loader resolves target repository from entity metadata.
     */
    public function getRepository(string|EntityInterface $entity): ?RepositoryInterface;

    /**
     * ### SQL builders
     *
     * These helpers build SQL and execute via this repository.
     */
    public function sqlQuery(): Query;

    public function sqlInsert(array $data): Insert;

    public function sqlUpdate(int|string|null $id = null, array $data = []): Update;

    public function sqlDelete(int|string|null $id = null): Delete;

    /**
     * ### Low-level execution helpers
     */

    /**
     * Executes SQL with parameters and returns a PDOStatement.
     *
     * Implementations may wrap PDO with logging/metrics and set fetch mode to assoc.
     *
     * @param string $sql
     * @param array $data
     * @return false|PDOStatement
     */
    public function execute(string $sql, array $data): bool|PDOStatement;

    /**
     * Executes SQL and hydrates results into entities.
     * 
     * @param string $sql
     * @param array $data
     * @return EntityInterface[]
     */
    public function fetch(string $sql, array $data = []): array;

    /**
     * Finds entities for a simple condition (column = value).
     *
     * @param int|string $id
     * @param string|null $column
     * @param string|null $orderBy
     * @return EntityInterface[]
     */
    public function find(int|string $id, ?string $column = null, ?string $orderBy = null): array;

    /**
     * Finds a single entity by ID.
     *
     * @param int|string $id
     * @return EntityInterface|null
     */
    public function findById(int|string $id): ?EntityInterface;

    /**
     * Creates a new entity instance from data (does not save to a database).
     *
     * Note: For more flexible entity creation (e.g., with additional parameters),
     * implement a custom method in your repository class (e.g., PostRepository::create).
     *
     * @param array $data
     * @return EntityInterface
     */
    public function createEntity(array $data): EntityInterface;

    /**
     * ### Persistence
     */

    /**
     * Saves an entity (insert or update).
     *
     * This is a convenience method that calls insertEntity() or updateEntity()
     * based on the entity state.
     */
    public function save(EntityInterface $entity): void;

    /**
     * Deletes an entity using the repository's default deletion policy.
     */
    public function delete(EntityInterface $entity): void;

    /**
     * ### Relations
     */

    /**
     * Gets the relation metadata for the given property name.
     *
     * @return HasMany|HasOne|BelongsTo|BelongsToOne|ManyToMany|null
     */
    public function getRelation(string $propertyName): mixed;

    /**
     * Loads the specified relation for the given entity or entities.
     *
     * @param EntityInterface|array<EntityInterface> $entities
     * @param string $relationName
     * @return Collection|EntityCollection
     *         Returns EntityCollection if the result contains EntityInterface instances, Collection otherwise.
     */
    public function load(EntityInterface|array $entities, string $relationName): Collection|EntityCollection;
}