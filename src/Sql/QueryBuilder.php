<?php

namespace WScore\DecaORM\Sql;

class QueryBuilder
{
    use WhereTrait;

    private string $queryType = 'SELECT';
    private array $selects = ['*'];
    private bool $distinct = false;
    private string $fromTable = '';
    private bool $fromRaw = false;
    private array $joins = [];
    private string $withSql = '';
    private array $groupBys = [];
    /** @var array<string> HAVING fragments (same AND style as WHERE) */
    private array $havings = [];
    /** @var array<string> */
    private array $orderBys = [];
    private ?int $offset = null;
    private ?int $limit = null;
    private bool $forUpdate = false;
    private bool $forUpdateNoWait = false;
    private bool $forUpdateSkipLocked = false;

    /**
     * Replace the SELECT column list (does not append).
     *
     * Each call discards columns accumulated by prior select(), addSelect(), and selectRaw().
     * There is no clearSelect(); call select() again to reset the list.
     * {@see Query} from sqlQuery() starts with "{$table}.*" — call select(...) when you need a different set.
     */
    public function select(string ...$columns): static
    {
        $this->selects = array_map(
            fn(string $column): string => $this->escapeColumnIdentifier($column),
            $columns
        );
        return $this;
    }

    /**
     * Append non-raw columns to SELECT.
     */
    public function addSelect(string ...$columns): static
    {
        foreach ($columns as $column) {
            $this->selects[] = $this->escapeColumnIdentifier($column);
        }
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
        $this->fromRaw = false;
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

    public function clearJoin(): static
    {
        $this->joins = [];
        $this->resetExpandedCache();
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
        $this->fromRaw = true;
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

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        if (preg_match('/\s/', $column) === 1 && stripos($column, ' AS ') === false) {
            throw new \InvalidArgumentException("ORDER BY column must not contain whitespace. Use orderByRaw() for raw expressions: {$column}");
        }

        $normalizedDirection = strtoupper(trim($direction));
        if (!in_array($normalizedDirection, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Unsupported ORDER BY direction: {$direction}");
        }

        $this->orderBys[] = $this->escapeColumnIdentifier($column) . ' ' . $normalizedDirection;
        return $this;
    }

    /**
     * 安全な ORDER BY 指定（識別子 + 方向の検証）。
     */
    public function orderByColumn(string $column, string $direction = 'ASC'): static
    {
        return $this->orderBy($column, $direction);
    }

    /**
     * 生の ORDER BY 断片を追加する。
     */
    public function orderByRaw(string $sqlSnippet): static
    {
        $this->orderBys[] = $sqlSnippet;
        return $this;
    }

    public function clearOrderBy(): static
    {
        $this->orderBys = [];
        return $this;
    }

    /**
     * @param string ...$columns Column expressions (e.g. 'u.id', 'DATE(created_at)').
     */
    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $column) {
            if (preg_match('/\s/', $column) === 1) {
                throw new \InvalidArgumentException(
                    "GROUP BY column must not contain whitespace. Use groupByRaw() for raw expressions: {$column}"
                );
            }
            $this->groupBys[] = $this->escapeColumnIdentifier($column);
        }
        return $this;
    }

    /**
     * Add a raw GROUP BY expression.
     */
    public function groupByRaw(string $sqlSnippet): static
    {
        $this->groupBys[] = $sqlSnippet;
        return $this;
    }

    public function clearGroupBy(): static
    {
        $this->groupBys = [];
        return $this;
    }

    /**
     * HAVING condition with bound value (placeholder generated like WHERE).
     */
    public function having(string $column, mixed $value, string $operator = '='): static
    {
        $operator = $this->validateOperator($operator);

        if (is_array($value)) {
            if ($operator !== '=') {
                throw new \InvalidArgumentException("Unsupported operator for array value: {$operator}");
            }
            if ($value === []) {
                $this->havings[] = '(1 = 0)';
                return $this;
            }

            $marker = $this->createPlaceholder('_EXPAND_having_' . $column);
            $this->havings[] = "{$this->escapeColumnIdentifier($column)} IN (:{$marker})\n";
            $this->parameters[$marker] = $value;
            return $this;
        }

        $escapedColumn = $this->escapeColumnIdentifier($column);
        if ($value === null) {
            if ($operator === '=' || $operator === 'IS') {
                $this->havings[] = "{$escapedColumn} IS NULL\n";
                return $this;
            }
            if ($operator === '!=' || $operator === '<>' || $operator === 'IS NOT') {
                $this->havings[] = "{$escapedColumn} IS NOT NULL\n";
                return $this;
            }
            throw new \InvalidArgumentException("Unsupported operator for NULL value: {$operator}");
        }

        $placeholder = $this->createPlaceholder('having_' . $column);
        $this->havings[] = "{$escapedColumn} {$operator} :{$placeholder}\n";
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
        $this->resetExpandedCache();
        return $this;
    }

    public function clearHaving(): static
    {
        $this->havings = [];
        $this->resetExpandedCache();
        return $this;
    }

    /**
     * Append FOR UPDATE (row lock). Supported by PostgreSQL, MySQL, etc. Not supported by SQLite.
     */
    public function forUpdate(bool $on = true, bool $noWait = false, bool $skipLocked = false): static
    {
        $this->forUpdate = $on;
        if (!$on) {
            $this->forUpdateNoWait = false;
            $this->forUpdateSkipLocked = false;
            return $this;
        }

        $this->forUpdateNoWait = $noWait;
        $this->forUpdateSkipLocked = $skipLocked;
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
        $select = empty($this->selects) ? '*' : implode(', ', $this->selects);
        $selectKeyword = $this->distinct ? 'SELECT DISTINCT' : $this->queryType;

        // FROM句
        $fromClause = $this->fromRaw ? $this->fromTable : $this->escapeTableReference($this->fromTable);
        $sql .= "{$selectKeyword} {$select} " . "\n" . "FROM {$fromClause}" . "\n";

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
        if ($this->orderBys !== []) {
            $sql .= 'ORDER BY ' . implode(', ', $this->orderBys) . "\n";
        }
        // LIMIT, OFFSET句
        if (isset($this->limit) && $this->limit > 0) {
            $sql .= "LIMIT {$this->limit}" . "\n";
        }
        if (isset($this->offset) && $this->offset > 0) {
            $sql .= "OFFSET {$this->offset}" . "\n";
        }

        if ($this->forUpdate && !$this->isSqliteDriver()) {
            $sql .= 'FOR UPDATE';
            if ($this->forUpdateNoWait) {
                $sql .= ' NOWAIT';
            }
            if ($this->forUpdateSkipLocked) {
                $sql .= ' SKIP LOCKED';
            }
            $sql .= "\n";
        }

        // 最終的な SQL 文字列に対してIN句の展開マーカーを置換
        // JOIN句などでもEXPANDマーカーが使われる可能性があるため、全体に対して適用
        $sql = $this->applyExpandedMarkers($sql);

        return $sql;
    }

    /**
     * Builds a COUNT(*) wrapper around a clone of this query (no limit/offset/for update).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function toCountSubquery(): array
    {
        $query = clone $this;
        $query->limit(null);
        $query->offset(null);
        $query->forUpdate(false);

        $countSql = "SELECT COUNT(*) AS aggregate_count FROM (\n{$query->getSql()}\n) AS aggregate_count_query";

        return [$countSql, $query->getParameters()];
    }

}