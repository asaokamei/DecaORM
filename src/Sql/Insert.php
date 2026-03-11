<?php

namespace WScore\DecaORM\Sql;

use PDOStatement;
use WScore\DecaORM\Contracts\RepositoryInterface;

class Insert
{
    private string $table;
    private array $data = [];
    public function __construct(private RepositoryInterface $repository)
    {
        $this->table = $this->repository->getHydrator()->getTableName();
    }

    public function execute(): bool|PDOStatement
    {
        $sql = $this->getSql();
        $data = $this->getParameters();
        return $this->repository->execute($sql, $data);
    }

    public function data(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getSql(): string
    {
        $select = [];
        $values = [];
        foreach ($this->data as $columnName => $value) {
            $select[] = $columnName;
            $values[] = ':' . $columnName;
        }
        $select = implode(', ', $select);
        $values = implode(', ', $values);
        return "INSERT INTO {$this->table} ({$select}) VALUES ({$values});";
    }

    public function getParameters(): array
    {
        return $this->data;
    }
}