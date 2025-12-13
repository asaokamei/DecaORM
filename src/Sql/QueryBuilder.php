<?php

namespace WScore\DecaORM\Sql;

class QueryBuilder
{
    use WhereTrait;

    private string $queryType = 'SELECT';
    private array $selects = ['*'];
    private string $fromTable = '';
    private array $joins = [];
    private string $withSql = '';
    private string $orderBy;
    private ?int $offset;
    private ?int $limit;

    public function select(string ...$columns): self
    {
        $this->selects = $columns;
        return $this;
    }

    public function from(string $table): self
    {
        $this->fromTable = $table;
        return $this;
    }

    // --- 複雑な句への対応（Raw） ---

    public function withRaw(string $cte_sql): self
    {
        $this->withSql = $cte_sql;
        return $this;
    }

    public function joinRaw(string $raw_join_sql): self
    {
        $this->joins[] = '  ' . $raw_join_sql . PHP_EOL;
        return $this;
    }

    // WHERE句関連のメソッドはWhereTraitで提供される

    public function limit(?int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function orderBy(string $column): self
    {
        $this->orderBy = $column;
        return $this;
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
            $sql .= "WITH " . $this->withSql . PHP_EOL;
        }

        // SELECT句
        $select = empty($this->selects) ? '*' : implode(', ', $this->selects);
        $sql .= "{$this->queryType} {$select} " . PHP_EOL . "FROM {$this->fromTable}" . PHP_EOL;

        // JOIN句
        if (!empty($this->joins)) {
            $sql .= implode('', $this->joins);
        }

        // WHERE句
        $whereClause = $this->buildWhereClause();
        if (!empty($whereClause)) {
            $sql .= "WHERE {$whereClause}" . PHP_EOL;
        }

        // ORDER BY
        if ($this->orderBy) {
            $sql .= "ORDER BY {$this->orderBy}" . PHP_EOL;
        }
        // LIMIT, OFFSET句
        if (isset($this->limit) && $this->limit > 0) {
            $sql .= "LIMIT {$this->limit}" . PHP_EOL;
        }
        if (isset($this->offset) && $this->offset > 0) {
            $sql .= "OFFSET {$this->offset}" . PHP_EOL;
        }

        // 最終的な SQL 文字列に対してIN句の展開マーカーを置換
        // JOIN句などでもEXPANDマーカーが使われる可能性があるため、全体に対して適用
        $sql = $this->applyExpandedMarkers($sql);

        return $sql;
    }

    /**
     * 最終的なバインディングパラメーターを取得する
     */
    public function getParameters(): array
    {
        return $this->getWhereParameters();
    }
}