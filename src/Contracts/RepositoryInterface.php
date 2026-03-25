<?php

namespace WScore\DecaORM\Contracts;

use PDO;
use PDOStatement;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Attribute\MorphTo;
use WScore\DecaORM\Attribute\MorphToOne;
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
    public function getHydrator(): HydratorInterface;

    /**
     * Gets the repository for the given entity (or entity class).
     *
     * Typical use: relation loader resolves target repository from entity metadata.
     */
    public function getRepository(string|EntityInterface $entity): RepositoryInterface;

    /**
     * ### SQL builders
     *
     * These helpers build SQL and execute via this repository.
     */
    public function sqlQuery(): Query;

    /**
     * Applies {@see RepositoryHooksInterface::beforeQuery()} for this repository.
     *
     * Invoked automatically from {@see sqlQuery()} and {@see Query::newQuery()}.
     * Call manually if you construct {@see Query} directly (unusual).
     */
    public function applyHooksToQuery(Query $query): void;

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
     * @return EntityCollection
     */
    public function fetch(string $sql, array $data = []): EntityCollection;

    /**
     * Executes SQL and yields one entity per row without {@see EntityCache} or DirtyTracker.
     *
     * For large reads (e.g. export). Returned instances are not tracked; treat as read-only snapshots.
     *
     * @return \Generator<int, EntityInterface>
     */
    public function fetchStream(string $sql, array $data = []): \Generator;

    /**
     * Finds entities by primary key or another column.
     *
     * - Scalar: `WHERE column = :id` (default ORDER BY is that column).
     * - Non-empty array: `WHERE column IN (...)` (ORDER BY only if $orderBy is not null).
     * - Empty array: empty result, no query.
     *
     * @param int|string|array<int|string> $id
     */
    public function find(int|string|array $id, ?string $column = null, ?string $orderBy = null): EntityCollection;

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
     * Checks if an entity is new (not yet saved to the database).
     *
     * @param EntityInterface $entity
     * @return bool
     */
    public function isNew(EntityInterface $entity): bool;

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
     * @return HasMany|HasOne|BelongsTo|BelongsToOne|MorphTo|MorphToOne|ManyToMany|null
     */
    public function getRelation(string $propertyName): mixed;

    /**
     * Loads the specified relation for the given entity or entities.
     *
     * @param EntityInterface|array<EntityInterface>|EntityCollection<EntityInterface> $entities Pass-through for a single entity; arrays are normalized to EntityCollection inside load().
     * @param string $relationName
     * @return Collection|EntityCollection
     *         Returns EntityCollection if the result contains EntityInterface instances, Collection otherwise.
     */
    public function load(EntityInterface|array|EntityCollection $entities, string $relationName): Collection|EntityCollection;
}