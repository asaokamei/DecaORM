<?php

namespace WScore\DecaORM;

use PDO;
use PDOStatement;

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
     * executes the sql with data and returns results as PDOStatement.
     *
     * @param string $sql
     * @param array $data
     * @return false|PDOStatement
     */

    public function execute(string $sql, array $data): false|PDOStatement;

    /**
     * executes the sql with data and returns results as entities.
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
}