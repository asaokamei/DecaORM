<?php

namespace WScore\DecaORM\Sql;

use PDOStatement;
use WScore\DecaORM\Contracts\RepositoryInterface;

class Insert
{
    use IdentifierQuoteTrait;

    private string $table;
    private array $data = [];

    public function __construct(private RepositoryInterface $repository)
    {
        $driverName = $this->repository->getDb()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->setIdentifierQuoteByDriver(is_string($driverName) ? $driverName : null);
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
        $columns = [];
        $values = [];
        foreach ($this->data as $columnName => $value) {
            $columns[] = $this->escapeColumnIdentifier((string) $columnName);
            $values[] = ':' . $columnName;
        }
        $columns = implode(', ', $columns);
        $values = implode(', ', $values);
        $table = $this->escapeTableReference($this->table);
        return "INSERT INTO {$table} ({$columns}) VALUES ({$values});";
    }

    public function getParameters(): array
    {
        return $this->data;
    }
}