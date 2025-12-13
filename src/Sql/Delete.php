<?php

namespace WScore\DecaORM\Sql;

use PDOStatement;
use WScore\DecaORM\RepositoryInterface;

class Delete
{
    use WhereTrait;

    private string $table;
    private string $pkColumn;

    public function __construct(private RepositoryInterface $repository)
    {
        $this->table = $this->repository->getTableName();
        $this->pkColumn = $this->repository->getPrimaryKeyColumn();
    }

    public function execute(): bool|PDOStatement
    {
        $sql = $this->getSql();
        $data = $this->getParameters();
        return $this->repository->execute($sql, $data);
    }

    /**
     * Set the primary key ID for simple update by ID.
     * This is a convenience method for the common case of updating by ID.
     * Can be combined with where() methods for more complex conditions.
     */
    public function setId(int|string $id): static
    {
        $this->where($this->pkColumn, $id);
        return $this;
    }

    // WHERE句関連のメソッド（where(), whereIn(), whereRaw()）はWhereTraitで提供される

    public function getSql(): string
    {
        // IN句の展開処理（未処理の場合のみ実行）
        if ($this->expanded_markers === null) {
            $this->processExtends();
        }

        // SET句の構築
        $values = [];

        // WHERE句の構築
        $whereConditions = $this->buildWhereClause();

        // WHERE句がない場合はエラー
        if (empty($whereConditions)) {
            throw new \RuntimeException('No WHERE condition specified. Use setId() or where() methods.');
        }

        // 最終的なSQL文字列を構築
        $sql = "DELETE FROM {$this->table} " . PHP_EOL
            . "WHERE {$whereConditions}";

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