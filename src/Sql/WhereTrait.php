<?php

namespace WScore\DecaORM\Sql;

trait WhereTrait
{
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
        $placeholder = $this->createPlaceholder($column);
        $this->wheres[] = "{$column} {$operator} :{$placeholder}" . "\n";
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
        $this->wheres[] = "{$column} IN (:{$marker})" . "\n";
        $this->parameters[$marker] = $values;
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
     * WHERE句のパラメータを取得
     * IN句の展開処理も実行される
     * 
     * @return array<string, mixed>
     */
    protected function getWhereParameters(): array
    {
        // IN句の展開処理（未処理の場合のみ実行）
        if ($this->expanded_params === null) {
            $this->processExtends();
        }
        return $this->expanded_params ?? $this->parameters;
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
     * IN句の展開マーカーを処理し、SQLテンプレートとパラメーターを展開する
     */
    protected function processExtends(): void
    {
        $expanded_markers = [];
        $expanded_params = $this->parameters;

        foreach ($expanded_params as $marker => $values) {
            // 配列値のみを処理（IN句の展開対象）
            if (is_array($values)) {
                // 元のマーカーをパラメータから削除
                unset($expanded_params[$marker]);
                
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
        return $this;
    }

    public function hasWhere(): bool
    {
        return $this->buildWhereClause() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        // SET/WHEREを含む全parametersを対象にIN展開する
        if ($this->expanded_params === null) {
            $this->processExtends();
        }
        return $this->expanded_params ?? $this->parameters;
    }
}

