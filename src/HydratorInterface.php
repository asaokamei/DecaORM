<?php

namespace WScore\DecaORM;

interface HydratorInterface
{
    /**
     * エンティティのクラス名
     */
    public function getEntityClass(): string;

    /**
     * DBテーブル名
     */
    public function getTableName(): string;
    /**
     * DBのプライマリキー
     */
    public function getPrimaryKey(): string;

    /**
     * エンティティのプロパティ一覧
     */
    public function listProperties(): array;

    /**
     * 登録日時のカラム名
     */
    public function getCreatedAt(): ?string;

    /**
     * 更新日時のカラム名
     */
    public function getUpdatedAt(): ?string;

    /**
     * 連想配列（DB行）からエンティティに変換（ハイドレーション）
     */
    public function hydrate(array $data): EntityInterface;

    /**
     * エンティティから連想配列に変換（デハイドレーション）
     */
    public function dehydrate(EntityInterface $entity): array;
}