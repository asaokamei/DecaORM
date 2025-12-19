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
        $this->sets[] = "{$column} = :{$placeholder}";
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
        return $this;
    }

    /**
     * set() のまとめ指定
     */
    public function data(array $data): static
    {
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

        $sql = "UPDATE {$this->table}" . PHP_EOL
            . "SET " . implode(', ', $this->sets) . PHP_EOL;

        $where = $this->buildWhereClause();
        if ($where !== '') {
            $sql .= "WHERE {$where}";
        }

        return $this->applyExpandedMarkers($sql);
    }
}
