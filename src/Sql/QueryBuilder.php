<?php

namespace WScore\DecaORM\Sql;

class QueryBuilder
{
    use WhereTrait;

    private string $queryType = 'SELECT';
    private array $selects = ['*'];
    private bool $distinct = false;
    private string $fromTable = '';
    private array $joins = [];
    private string $withSql = '';
    private array $groupBys = [];
    /** @var array<string> HAVING fragments (same AND style as WHERE) */
    private array $havings = [];
    private ?string $orderBy = null;
    private ?int $offset = null;
    private ?int $limit = null;
    private bool $forUpdate = false;

    public function select(string ...$columns): static
    {
        $this->selects = $columns;
        return $this;
    }

    /**
     * When true, generates SELECT DISTINCT. Default is false.
     */
    public function distinct(bool $on = true): static
    {
        $this->distinct = $on;
        return $this;
    }

    public function from(string $table): static
    {
        $this->fromTable = $table;
        return $this;
    }


    // --- 複雑な句への対応（Raw） ---

    public function withRaw(string $cte_sql): static
    {
        $this->withSql = $cte_sql;
        return $this;
    }

    public function joinRaw(string $raw_join_sql): static
    {
        $this->joins[] = '  ' . $raw_join_sql . "\n";
        return $this;
    }

    /**
     * Append a raw SELECT expression (comma-separated after existing columns).
     * Bindings merge into the query bag (same as whereRaw); :_EXPAND_ markers work in the expression.
     * Call {@see select()} first if you must replace the default column list (avoid `*, expr` mistakes).
     */
    public function selectRaw(string $expression, array $bindings = []): static
    {
        $this->selects[] = $expression;
        $this->parameters = array_merge($this->parameters, $bindings);
        return $this;
    }

    /**
     * Set the FROM clause from a raw fragment (subquery, alias, etc.).
     * Bindings merge into the query bag; :_EXPAND_ inside the fragment is expanded like other clauses.
     */
    public function fromRaw(string $fragment, array $bindings = []): static
    {
        $this->fromTable = $fragment;
        $this->parameters = array_merge($this->parameters, $bindings);
        return $this;
    }

    // WHERE句関連のメソッドはWhereTraitで提供される

    public function limit(?int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(?int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function orderBy(string $column): static
    {
        $this->orderBy = $column;
        return $this;
    }

    /**
     * @param string ...$columns Column expressions (e.g. 'u.id', 'DATE(created_at)').
     */
    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $column) {
            $this->groupBys[] = $column;
        }
        return $this;
    }

    /**
     * HAVING condition with bound value (placeholder generated like WHERE).
     */
    public function having(string $column, mixed $value, string $operator = '='): static
    {
        $operator = $this->validateOperator($operator);
        $placeholder = $this->createPlaceholder('having_' . $column);
        $this->havings[] = "{$this->escapeColumnIdentifier($column)} {$operator} :{$placeholder}\n";
        $this->parameters[$placeholder] = $value;
        return $this;
    }

    /**
     * Raw HAVING fragment with optional bound parameters (merged into the query parameter bag).
     */
    public function havingRaw(string $sql_snippet, array $bindings = []): static
    {
        $this->havings[] = $sql_snippet . "\n";
        $this->parameters = array_merge($this->parameters, $bindings);
        return $this;
    }

    /**
     * Append FOR UPDATE (row lock). Supported by PostgreSQL, MySQL, etc. Not supported by SQLite.
     */
    public function forUpdate(bool $on = true): static
    {
        $this->forUpdate = $on;
        return $this;
    }

    protected function buildHavingClause(): string
    {
        if ($this->havings === []) {
            return '';
        }
        return implode('  AND ', $this->havings);
    }

    // --- SQLとパラメーターの取得 ---

    /**
     * 最終的なSQLテンプレートを生成する
     */
    public function getSql(): string
    {
        // IN句の展開処理（未処理の場合のみ実行）
        if ($this->expanded_markers === null) {
            $this->processExtends();
        }

        $sql = '';

        // WITH句
        if (!empty($this->withSql)) {
            $sql .= "WITH " . $this->withSql . "\n";
        }

        // SELECT句
        $escapedSelects = array_map(fn(string $column): string => $this->escapeColumnIdentifier($column), $this->selects);
        $select = empty($escapedSelects) ? '*' : implode(', ', $escapedSelects);
        $selectKeyword = $this->distinct ? 'SELECT DISTINCT' : $this->queryType;
        $sql .= "{$selectKeyword} {$select} " . "\n" . "FROM {$this->escapeTableReference($this->fromTable)}" . "\n";

        // JOIN句
        if (!empty($this->joins)) {
            $sql .= implode('', $this->joins);
        }

        // WHERE句
        $whereClause = $this->buildWhereClause();
        if (!empty($whereClause)) {
            $sql .= "WHERE {$whereClause}" . "\n";
        }

        if ($this->groupBys !== []) {
            $sql .= 'GROUP BY ' . implode(', ', $this->groupBys) . "\n";
        }

        $havingClause = $this->buildHavingClause();
        if ($havingClause !== '') {
            $sql .= "HAVING {$havingClause}\n";
        }

        // ORDER BY
        if ($this->orderBy) {
            $sql .= "ORDER BY {$this->orderBy}" . "\n";
        }
        // LIMIT, OFFSET句
        if (isset($this->limit) && $this->limit > 0) {
            $sql .= "LIMIT {$this->limit}" . "\n";
        }
        if (isset($this->offset) && $this->offset > 0) {
            $sql .= "OFFSET {$this->offset}" . "\n";
        }

        if ($this->forUpdate) {
            $sql .= "FOR UPDATE\n";
        }

        // 最終的な SQL 文字列に対してIN句の展開マーカーを置換
        // JOIN句などでもEXPANDマーカーが使われる可能性があるため、全体に対して適用
        $sql = $this->applyExpandedMarkers($sql);

        return $sql;
    }

}