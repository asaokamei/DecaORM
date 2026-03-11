<?php

namespace WScore\DecaORM\Sql;

use PDOStatement;
use WScore\DecaORM\Contracts\RepositoryInterface;

class DeleteBuilder
{
    use WhereTrait;

    protected string $table;

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function getSql(): string
    {
        // IN句の展開処理（未処理の場合のみ実行）
        if ($this->expanded_markers === null) {
            $this->processExtends();
        }

        // 最終的なSQL文字列を構築
        $sql = "DELETE FROM {$this->table} ";

        // WHERE句の構築
        $whereConditions = $this->buildWhereClause();

        // WHERE句がない場合はエラー
        if ($whereConditions !== '') {
            $sql .= PHP_EOL . "WHERE {$whereConditions}";
        }


        // IN句の展開マーカーを適用
        $sql = $this->applyExpandedMarkers($sql);

        return $sql;
    }

    public function getParameters(): array
    {
        // SET句のデータとWHERE句のパラメータを統合
        return $this->getWhereParameters();
    }
}