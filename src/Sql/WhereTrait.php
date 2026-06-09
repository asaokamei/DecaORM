<?php

namespace WScore\DecaORM\Sql;

use InvalidArgumentException;

trait WhereTrait
{
    use IdentifierQuoteTrait;
    /** @var array<string> WHERE条件の配列 */
    protected array $wheres = [];
    
    /** @var array<string, mixed> パラメータの配列 */
    protected array $parameters = [];
    
    /** @var int プレースホルダーの衝突を防ぐためのカウンター */
    protected int $placeholder_counter = 0;
    
    /** @var array<string, string>|null IN句展開用のマーカー（null=未処理、空配列=処理済みだがIN句なし） */
    protected ?array $expanded_markers = null;
    
    /** @var array<string, mixed>|null 展開後のパラメータ（null=未処理） */
    protected ?array $expanded_params = null;

    /**
     * Enum値を変換するユーティリティメソッド
     * 
     * @param mixed $value
     * @return mixed
     */
    protected function convertEnum($value)
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        return $value;
    }

    /**
     * 生のWHERE句（OR条件のグループ化など）を追加し、バインディングをマージする
     * @param string $sql_snippet (例: (age < :min_age OR score > :max_score))
     * @param array $bindings プレースホルダーと値のマップ
     */
    public function whereRaw(string $sql_snippet, array $bindings = []): static
    {
        $this->wheres[] = $sql_snippet . "\n";
        $this->parameters = array_merge($this->parameters, $bindings);
        return $this;
    }

    /**
     * 基本的なWHERE条件を追加
     * 
     * @param string $column カラム名
     * @param mixed $value 値
     * @param string $operator 演算子（デフォルト: '='）
     */
    public function where(string $column, $value, string $operator = '='): static
    {
        $operator = $this->validateOperator($operator);

        if (is_array($value)) {
            if ($operator !== '=') {
                throw new InvalidArgumentException("Unsupported operator for array value: {$operator}");
            }
            return $this->whereIn($column, $value);
        }

        $escapedColumn = $this->escapeColumnIdentifier($column);
        if ($value === null) {
            if ($operator === '=' || $operator === 'IS') {
                $this->wheres[] = "{$escapedColumn} IS NULL\n";
                return $this;
            }
            if ($operator === '!=' || $operator === '<>' || $operator === 'IS NOT') {
                $this->wheres[] = "{$escapedColumn} IS NOT NULL\n";
                return $this;
            }
            throw new InvalidArgumentException("Unsupported operator for NULL value: {$operator}");
        }

        $placeholder = $this->createPlaceholder($column);
        $this->wheres[] = "{$escapedColumn} {$operator} :{$placeholder}" . "\n";
        $this->parameters[$placeholder] = $value;
        return $this;
    }

    /**
     * WHERE IN条件を追加
     * 
     * @param string $column カラム名
     * @param array $values 値の配列
     */
    public function whereIn(string $column, array $values): static
    {
        if (empty($values)) {
            // 空の配列の場合、結果を返さない安全な条件を追加
            $this->wheres[] = "(1 = 0)";
            return $this;
        }

        // 展開が必要なことを示すマーカープレースホルダーを使用
        $marker = $this->createPlaceholder('_EXPAND_' . $column);
        $escapedColumn = $this->escapeColumnIdentifier($column);
        $this->wheres[] = "{$escapedColumn} IN (:{$marker})" . "\n";
        $this->parameters[$marker] = $values;
        return $this;
    }

    /**
     * WHERE NOT IN条件を追加
     *
     * @param string $column カラム名
     * @param array $values 値の配列
     */
    public function whereNotIn(string $column, array $values): static
    {
        if (empty($values)) {
            // 空配列は除外条件がないため常に真
            $this->wheres[] = '(1 = 1)';
            return $this;
        }

        $marker = $this->createPlaceholder('_EXPAND_' . $column);
        $escapedColumn = $this->escapeColumnIdentifier($column);
        $this->wheres[] = "{$escapedColumn} NOT IN (:{$marker})" . "\n";
        $this->parameters[$marker] = $values;
        return $this;
    }

    public function clearWhere(): static
    {
        $this->wheres = [];
        $this->resetExpandedCache();
        return $this;
    }

    /**
     * WHERE句を構築して返す（マーカーは展開されていない状態）
     * 
     * @return string WHERE句（WHEREキーワードは含まない、マーカーは未展開）
     */
    protected function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        // WHERE句を結合（マーカーはまだ展開されていない状態）
        // マーカーの展開はgetSql()で行う
        return implode('  AND ', $this->wheres);
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        // IN句の展開処理（未処理の場合のみ実行）
        if ($this->expanded_params === null) {
            $this->processExtends();
        }
        $params = $this->expanded_params ?? $this->parameters;

        // 全パラメータに対してEnum変換を一括適用
        foreach ($params as $key => $value) {
            $params[$key] = $this->convertEnum($value);
        }

        return $params;
    }

    /**
     * ユニークなプレースホルダー名を生成する
     */
    protected function createPlaceholder(string $baseName): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $baseName);
        return $name . '_' . $this->placeholder_counter++;
    }

    /**
     * WHERE/HAVING で利用する比較演算子を検証する。
     */
    protected function validateOperator(string $operator): string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $operator) ?? ''));
        $allowed = [
            '=', '!=', '<>',
            '<', '<=', '>', '>=',
            'LIKE', 'NOT LIKE',
            'IS', 'IS NOT',
        ];

        if (!in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported operator: {$operator}");
        }

        return $normalized;
    }

    /**
     * IN句の展開マーカーを処理し、SQLテンプレートとパラメーターを展開する
     */
    protected function processExtends(): void
    {
        $expanded_markers = [];
        $expanded_params = [];

        foreach ($this->parameters as $marker => $values) {
            // 配列値のみを処理（IN句の展開対象）
            if (is_array($values)) {
                // マーカーが既に_EXPAND_で始まっているか確認
                // whereIn()で生成されたマーカーは既に_EXPAND_で始まっている（例: _EXPAND_u_id_0）
                // setParameters()で設定されたマーカーは_EXPAND_で始まっていない（例: user_id）
                $original_marker_key = $marker;
                if (!str_starts_with($marker, '_EXPAND_')) {
                    // setParameters()で設定された場合、_EXPAND_プレフィックスを追加
                    // SQLテンプレートでは :_EXPAND_user_id の形式で使われている
                    $original_marker_key = '_EXPAND_' . $marker;
                }

                // 各値に対して新しいプレースホルダーを生成
                // プレースホルダー名は一意である必要があるため、元のマーカーキーにインデックスを付加
                $new_placeholders = [];
                foreach ($values as $index => $value) {
                    // 新しいプレースホルダー名を生成（例: _EXPAND_u_id_0_0, _EXPAND_u_id_0_1, ...）
                    $new_placeholder = $original_marker_key . '_' . $index;
                    $new_placeholders[] = ':' . $new_placeholder;
                    $expanded_params[$new_placeholder] = $value;
                }

                // SQLテンプレート内のマーカー（コロン付き）を置換用に登録
                // whereIn()の場合: ":_EXPAND_u_id_0" → ":_EXPAND_u_id_0_0, :_EXPAND_u_id_0_1, ..."
                // setParameters()の場合: ":_EXPAND_user_id" → ":_EXPAND_user_id_0, :_EXPAND_user_id_1, ..."
                $expanded_markers[':' . $original_marker_key] = implode(', ', $new_placeholders);
            } else {
                // 配列でない場合はそのまま追加
                $expanded_params[$marker] = $values;
            }
        }

        $this->expanded_markers = $expanded_markers;
        $this->expanded_params = $expanded_params;
    }

    /**
     * 展開されたマーカーをSQL文字列に適用する
     * 
     * @param string $sql SQL文字列
     * @return string マーカーが置換されたSQL文字列
     */
    protected function applyExpandedMarkers(string $sql): string
    {
        if (!empty($this->expanded_markers)) {
            foreach ($this->expanded_markers as $marker => $replacement) {
                $sql = str_replace($marker, $replacement, $sql);
            }
        }
        return $sql;
    }

    public function setParameters(array $array): static
    {
        $this->parameters = array_merge($this->parameters, $array);
        $this->resetExpandedCache();
        return $this;
    }

    public function setParameter(string $key, mixed $value): static
    {
        $this->parameters[$key] = $value;
        $this->resetExpandedCache();
        return $this;
    }

    public function clearParameters(): static
    {
        $this->parameters = [];
        $this->resetExpandedCache();
        return $this;
    }

    protected function resetExpandedCache(): void
    {
        $this->expanded_markers = null;
        $this->expanded_params = null;
    }

    public function hasWhere(): bool
    {
        return $this->buildWhereClause() !== '';
    }

}

