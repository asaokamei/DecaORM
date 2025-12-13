<?php

namespace WScore\DecaORM;

use PDO;
use PDOStatement;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;

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

    /**
     * executes the SQL with data and returns results as PDOStatement.
     *
     * @param string $sql
     * @param array $data
     * @return false|PDOStatement
     */

    public function execute(string $sql, array $data): false|PDOStatement;

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
    public function find(int|string $id, string $column = null, string $orderBy = null): array;

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
     * Gets the relation for the given property name.
     * @return HasMany|HasOne|BelongsTo|BelongsToOne|null
     */
    public function getRelation(string $propertyName): mixed;

    /**
     * Loads the specified relation for the given entity.
     * 
     * @param EntityInterface $entity The entity for which the relation is to be loaded.
     * @param HasMany|HasOne|BelongsTo|BelongsToOne $relation The relation to be loaded; must be an instance of a supported relation type.
     * @return void
     */
    public function load(EntityInterface $entity, mixed $relation): void;

    /**
     * Fills the specified relation for the given entity.
     * 
     * @param EntityInterface $entity The entity for which the relation is to be filled.
     * @param string $relationName The name of the relation property to fill.
     * @return void
     */
    public function fill(EntityInterface $entity, string $relationName): void;
}