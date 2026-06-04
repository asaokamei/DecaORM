<?php

namespace WScore\DecaORM\Sql;

use PDOStatement;
use WScore\DecaORM\Contracts\RepositoryInterface;

class Insert
{
    use IdentifierQuoteTrait;

    private string $table;
    private array $data = [];
    private int $placeholderCounter = 0;
    /** @var array<string, string> */
    private array $placeholderMap = [];

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
        $this->placeholderCounter = 0;
        $this->placeholderMap = [];
        return $this;
    }

    private function createPlaceholder(string $baseName): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $baseName);
        return $name . '_' . $this->placeholderCounter++;
    }

    public function getSql(): string
    {
        $this->placeholderCounter = 0;
        $this->placeholderMap = [];
        $columns = [];
        $values = [];
        foreach ($this->data as $columnName => $_value) {
            $columnName = (string) $columnName;
            $placeholder = $this->createPlaceholder($columnName);
            $columns[] = $this->escapeColumnIdentifier((string) $columnName);
            $values[] = ':' . $placeholder;
            $this->placeholderMap[$placeholder] = $columnName;
        }
        $columns = implode(', ', $columns);
        $values = implode(', ', $values);
        $table = $this->escapeTableReference($this->table);
        return "INSERT INTO {$table} ({$columns}) VALUES ({$values});";
    }

    public function getParameters(): array
    {
        if (empty($this->placeholderMap) && !empty($this->data)) {
            $this->getSql();
        }

        if (empty($this->placeholderMap)) {
            return [];
        }

        $parameters = [];
        foreach ($this->placeholderMap as $placeholder => $columnName) {
            $parameters[$placeholder] = $this->data[$columnName];
        }

        return $parameters;
    }
}