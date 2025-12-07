<?php

namespace WScore\DecaORM;

interface RepositoryInterface
{
    /**
     * @return string
     */
    public function getTableName(): string;
    
    /**
     * retrieve entities from sql and data.
     * 
     * @param string $sql
     * @param array $data
     * @return EntityInterface[]
     */
    public function fetchClass(string $sql, array $data = []): array;

    /**
     * get array of column and property names;
     *
     * @return array<string,string>   array of column name to property name mapping (e.g. ['user_id' => 'id', 'user_name' => 'name'])
     */
    public function listColumnsToProperties(): array;
}