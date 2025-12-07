<?php

namespace WScore\DecaORM\Sql;

class QueryBuilder
{
    private string $queryType = 'SELECT';
    private array $selects = ['*'];
    private string $fromTable = '';
    private array $joins = [];
    private array $wheres = [];
    private array $parameters = [];
    private string $withSql = '';
    private int $placeholder_counter = 0; // プレースホルダーの衝突を防ぐためのカウンター
    private string $orderBy;
    private int $offset;
    private int $limit;
    private array $expanded_markers;
    private array $expanded_params;

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

    /**
     * 生のWHERE句（OR条件のグループ化など）を追加し、バインディングをマージする
     * @param string $sql_snippet (例: (age < :min_age OR score > :max_score))
     * @param array $bindings プレースホルダーと値のマップ
     */
    public function whereRaw(string $sql_snippet, array $bindings = []): self
    {
        $this->wheres[] = $sql_snippet . PHP_EOL;
        $this->parameters = array_merge($this->parameters, $bindings);
        return $this;
    }

    // --- 基本的な WHERE 句と IN 句の処理 ---

    public function where(string $column, $value, string $operator = '='): self
    {
        // ユニークなプレースホルダー名生成
        $placeholder = $this->createPlaceholder($column);

        $this->wheres[] = "{$column} {$operator} :{$placeholder}" . PHP_EOL;
        $this->parameters[$placeholder] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // 空の配列の場合、結果を返さない安全な条件を追加
            $this->wheres[] = "(1 = 0)";
            return $this;
        }

        // 展開が必要なことを示すマーカープレースホルダーを使用
        // このキーで値の配列を格納
        $marker = $this->createPlaceholder('_EXPAND_' . $column);

        $this->wheres[] = "{$column} IN (:{$marker})" . PHP_EOL;
        $this->parameters[$marker] = $values;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
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
        // ステップ1: IN句の展開処理（SQLとパラメーターの展開）
        if (!isset($this->expanded_markers)) {
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
        if (!empty($this->wheres)) {
            $where_clause = implode('  AND ', $this->wheres);

            // 展開後の SQL テンプレートを使用
            $sql .= "WHERE {$where_clause}" . PHP_EOL;
        }

        // ORDER BY
        if ($this->orderBy) {
            $sql .= "ORDER BY {$this->orderBy}" . PHP_EOL;
        }
        // LIMIT, OFFSET句
        if ($this->limit) {
            $sql .= "LIMIT {$this->limit}" . PHP_EOL;
        }
        if ($this->offset) {
            $sql .= "OFFSET {$this->offset}" . PHP_EOL;
        }

        // 最終的な SQL 文字列に対して置換処理を行う
        foreach ($this->expanded_markers as $marker => $replacement) {
            $sql = str_replace($marker, $replacement, $sql);
        }

        return $sql;
    }

    /**
     * 最終的なバインディングパラメーターを取得する
     */
    public function getParameters(): array
    {
        // 最終的なSQL生成時に展開されたパラメーターを返す
        if (!isset($this->expanded_params)) {
            $this->processExtends();
        }
        return $this->expanded_params;
    }

    // --- 内部ヘルパーメソッド ---

    /**
     * ユニークなプレースホルダー名を生成する
     */
    private function createPlaceholder(string $baseName): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $baseName);
        return $name . '_' . $this->placeholder_counter++;
    }

    /**
     * IN句の展開マーカーを処理し、SQLテンプレートとパラメーターを展開する
     */
    private function processExtends(): void
    {
        $expanded_markers = []; // ['マーカー' => '置換後のプレースホルダーリスト']
        $expanded_params = $this->parameters;

        foreach ($expanded_params as $marker => $values) {
            // '_EXPAND_' マーカーを持つ配列値のみを処理
            if (is_array($values)) {
                unset($expanded_params[$marker]);
                if (!str_starts_with($marker, '_EXPAND_')) {
                    $marker = '_EXPAND_' . $marker;
                }

                $new_placeholders = [];
                $original_marker_key = $marker;

                // 古いマーカーを削除

                // 配列要素ごとに新しいユニークなプレースホルダーを生成
                foreach ($values as $index => $value) {
                    // ユニークな名前を生成 (例: :ext_status_0, :ext_status_1)
                    $new_placeholder = $original_marker_key . '_' . $index;
                    $new_placeholders[] = ':' . $new_placeholder;

                    // 新しいパラメーターとして格納
                    $expanded_params[$new_placeholder] = $value;
                }

                // SQL置換テンプレートを生成 (例: ':_EXPAND_status' => ':ext_status_0, :ext_status_1')
                $expanded_markers[':' . $original_marker_key] = implode(', ', $new_placeholders);
            }
        }

        $this->expanded_markers = $expanded_markers;
        $this->expanded_params = $expanded_params;
    }

    public function setParameters(array $array): static
    {
        $this->parameters = array_merge($this->parameters, $array);
        return $this;
    }
}