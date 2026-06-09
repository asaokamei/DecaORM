<?php

namespace WScore\DecaORM\Sql;

use PDO;
use PDOStatement;
use RuntimeException;
use WScore\DecaORM\Contracts\RepositoryInterface;

class Insert
{
    use IdentifierQuoteTrait;

    private string $table;
    private array $data = [];
    private int $placeholderCounter = 0;
    /** @var array<string, string> */
    private array $placeholderMap = [];
    private ?string $returningColumn = null;
    private ?PDOStatement $lastStatement = null;

    public function __construct(private RepositoryInterface $repository)
    {
        $driverName = $this->repository->getDb()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->setIdentifierQuoteByDriver(is_string($driverName) ? $driverName : null);
        $this->table = $this->repository->getTableName();
    }

    /**
     * Request primary-key (or other) value via RETURNING on PostgreSQL.
     * On other drivers the clause is omitted and {@see lastInsertId()} falls back to PDO.
     */
    public function returning(string $column): static
    {
        $this->returningColumn = $column;
        return $this;
    }

    public function execute(): bool|PDOStatement
    {
        $sql = $this->getSql();
        $data = $this->getParameters();
        $result = $this->repository->execute($sql, $data);
        $this->lastStatement = $result instanceof PDOStatement ? $result : null;

        return $result;
    }

    /**
     * Resolves the inserted row id after {@see execute()}.
     * Uses the RETURNING result set on PostgreSQL when {@see returning()} was set.
     */
    public function lastInsertId(): string|int|false
    {
        if ($this->lastStatement === null) {
            throw new RuntimeException('Insert::execute() must be called before lastInsertId().');
        }

        if ($this->returningColumn !== null && $this->isPgsql()) {
            $row = $this->lastStatement->fetch(PDO::FETCH_ASSOC);
            if ($row === false || !array_key_exists($this->returningColumn, $row)) {
                return false;
            }

            return $row[$this->returningColumn];
        }

        return $this->repository->getDb()->lastInsertId();
    }

    /**
     * Replace the INSERT column map (does not append).
     *
     * Same contract as {@see UpdateBuilder::data()}: bulk-assign writable columns for this statement.
     */
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
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$values})";
        if ($this->returningColumn !== null && $this->isPgsql()) {
            $sql .= ' RETURNING ' . $this->escapeColumnIdentifier($this->returningColumn);
        }

        return $sql . ';';
    }

    private function isPgsql(): bool
    {
        return $this->repository->getDb()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
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