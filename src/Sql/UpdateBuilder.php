<?php

namespace WScore\DecaORM\Sql;

use InvalidArgumentException;

class UpdateBuilder
{
    use WhereTrait;

    protected string $table = '';

    /** @var array<int, string> */
    protected array $sets = [];

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }


    /**
     * SET col = :set_col_n
     */
    public function set(string $column, mixed $value): static
    {
        $placeholder = $this->createPlaceholder('set_' . $column);
        $escapedColumn = $this->escapeColumnIdentifier($column);
        $this->sets[] = "{$escapedColumn} = :{$placeholder}";
        $this->parameters[$placeholder] = $value;
        return $this;
    }


    /**
     * SET断片（必要なら）
     */
    public function setRaw(string $sqlSnippet, array $bindings = []): static
    {
        $this->sets[] = $sqlSnippet;
        $this->parameters = array_merge($this->parameters, $bindings);
        $this->resetExpandedCache();
        return $this;
    }

    public function clearSet(): static
    {
        $this->sets = [];
        $this->resetExpandedCache();
        return $this;
    }

    /**
     * Replace the SET column map (does not append).
     *
     * Same contract as {@see Insert::data()}: bulk-assign writable columns for this statement.
     * Use {@see set()} / {@see setRaw()} to append after this call.
     */
    public function data(array $data): static
    {
        $this->clearSet();
        foreach ($data as $column => $value) {
            $this->set((string) $column, $value);
        }
        return $this;
    }

    public function getSql(): string
    {
        if ($this->table === '') {
            throw new InvalidArgumentException('Table name is not set. Call table().');
        }
        if (empty($this->sets)) {
            throw new InvalidArgumentException('No SET clause specified. Call set() or data().');
        }

        // IN句の展開処理（未処理の場合のみ実行）
        if ($this->expanded_markers === null) {
            $this->processExtends();
        }

        $sql = "UPDATE {$this->escapeTableReference($this->table)}" . "\n"
            . "SET " . implode(', ', $this->sets) . "\n";

        $where = $this->buildWhereClause();
        if ($where !== '') {
            $sql .= "WHERE {$where}";
        }

        return $this->applyExpandedMarkers($sql);
    }
}
