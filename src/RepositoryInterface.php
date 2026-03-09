<?php

namespace WScore\DecaORM;

use PDO;
use PDOStatement;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\ManyToMany;
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

    /**
     * returns the database table name of this repository.
     *
     * @return string
     */
    public function getTableName(): string;

    /**
     * returns the primary key column name of this table.
     *
     * @return string
     */
    public function getPrimaryKeyColumn(): string;

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
     * @return T[]
     */
    public function fetch(string $sql, array $data = []): array;

    /**
     * finds entities for a simple condition.
     *
     * @param int|string $id
     * @param string|null $column
     * @param string|null $orderBy
     * @return T[]
     */
    public function find(int|string $id, ?string $column = null, ?string $orderBy = null): array;

    /**
     * get an array of column and property names;
     *
     * @return array<string,string>   array of column name to property name mapping (e.g. ['user_id' => 'id', 'user_name' => 'name'])
     */
    public function listColumnsToProperties(): array;

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
     * @return T|null
     */
    public function findById(int|string $id): ?EntityInterface;

    /**
     * Creates a new entity from data (does not save to database).
     * 
     * Note: For more flexible entity creation (e.g., with additional parameters),
     * implement a custom method in your repository class (e.g., PostRepository::create).
     * 
     * @param array $data
     * @return EntityInterface
     */
    public function createEntity(array $data): EntityInterface;

    /**
     * Inserts an entity into the database.
     * 
     * @param EntityInterface $entity
     * @return void
     */
    public function insertEntity(EntityInterface $entity): void;

    /**
     * Updates an entity in the database.
     * 
     * @param EntityInterface $entity
     * @return void
     */
    public function updateEntity(EntityInterface $entity): void;

    /**
     * Deletes an entity from the database.
     * 
     * @param EntityInterface $entity
     * @return void
     */
    public function deleteEntity(EntityInterface $entity): void;

    /**
     * Saves an entity (insert or update).
     * 
     * This is a convenience method that calls insertEntity() or updateEntity() based on the entity state.
     * 
     * @param EntityInterface $entity
     * @return void
     */
    public function save(EntityInterface $entity): void;
}