<?php

namespace WScore\DecaORM;

use PDO;
use PDOStatement;

/**
 * @template T of EntityInterface
 */
interface RepositoryInterface
{
    public function getDb(): PDO;

    public function getTableName(): string;

    public function execute(string $sql, array $data): false|PDOStatement;

    /**
     * retrieve entities from sql and data.
     * 
     * @param string $sql
     * @param array $data
     * @return T[]
     */
    public function fetchClass(string $sql, array $data = []): array;

    /**
     * a simple method for retrieving entities from sql and data.
     *
     * @param int|string $id
     * @param string|null $column
     * @param string|null $orderBy
     * @return T[]
     */
    public function fetch(int|string $id, string $column = null, string $orderBy = null): array;

    /**
     * get array of column and property names;
     *
     * @return array<string,string>   array of column name to property name mapping (e.g. ['user_id' => 'id', 'user_name' => 'name'])
     */
    public function listColumnsToProperties(): array;
}