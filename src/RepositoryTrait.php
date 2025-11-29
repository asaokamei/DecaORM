<?php

namespace WScore\DecaORM;

use DateTimeInterface;
use PDO;
use PDOStatement;

trait RepositoryTrait
{
    protected PDO $db;
    protected HydratorInterface $hydrator;
    protected DateTimeInterface $now;

    public function getDb(): PDO
    {
        return $this->db;
    }

    public function execute(string $sql, array $data): false|PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        return $stmt;
    }

    /**
     * @return EntityInterface[]
     */
    public function fetchAll(string $sql, array $data): array
    {
        $stmt = $this->execute($sql, $data);
        if (!$stmt) {
            return [];
        }
        $entityClass = $this->hydrator->getEntityClass();
        $stmt->setFetchMode(PDO::FETCH_CLASS, $entityClass);
        $list = $stmt->fetchAll();

        foreach ($list as $idx => $entity) {
            $list[$idx] = EntityCache::cache($entity);
        }

        return $list;
    }

    public function fetch(string $sql, array $data): ?EntityInterface
    {
        $list = $this->fetchAll($sql, $data);
        return $list[0] ?? null;
    }

    /**
     * PrimaryKeyからのエンティティの読込。
     *
     * @return ?EntityInterface
     */
    private function fetchEntityById(int|string $id): ?EntityInterface
    {
        $pKey = $this->hydrator->getPrimaryKey();

        return $this->fetch(
            "SELECT * FROM {$this->hydrator->getTableName()} WHERE {$pKey} = :id",
            [':id' => $id]
        );
    }

    private function insertEntity(EntityInterface $entity): void
    {
        $pKey = $this->hydrator->getPrimaryKey();
        $data = $this->hydrator->dehydrate($entity);
        if ($this->hydrator->isPkAutoNumber()) {
            unset($data[$pKey]);
        }
        $id = $this->insertData($data);
        if ($this->hydrator->isPkAutoNumber() && $id) {
            $entity->set($pKey, $id);
            EntityCache::cache($entity);
        }
        EntityCache::cache($entity);
    }

    /**
     * エンティティの更新
     */
    private function updateEntity(EntityInterface $entity): void
    {
        $pKey = $this->hydrator->getPrimaryKey();
        $data = $this->hydrator->dehydrate($entity);
        $values = [];

        // Remove PK!
        unset($data[$pKey]);
        // Remove CreatedAt!
        $createdAt = $this->hydrator->getCreatedAt();
        if ($createdAt) {
            unset($data[$createdAt]);
        }
        // Update UpdatedAt!
        $updatedAt = $this->hydrator->getUpdatedAt();
        if ($updatedAt !== null) {
            $data[$updatedAt] = $this->now->format('Y-m-d H:i:s');
        }
        foreach ($data as $item => $value) {
            $values[] = "{$item} = :{$item}";
        }

        $values = implode(', ', $values);

        $data[$pKey] = $entity->getId();
        $this->execute(
            "
            UPDATE {$this->hydrator->getTableName()} 
                SET {$values} 
                WHERE {$pKey} = :{$pKey}",
            $data // $dataにはidも含まれている
        );
    }

    /**
     * エンティティの削除
     */
    private function deleteEntity(EntityInterface $entity): void
    {
        $pKey = $this->hydrator->getPrimaryKey();
        $id = $entity->getId();
        $this->execute(
            "
            DELETE FROM {$this->hydrator->getTableName()} 
                   WHERE {$pKey} = :id",
            ['id' => $id]
        );
    }

    /**
     * Many-To-One：親エンティティを読み込む
     */
    private function fillParentEntity(
        EntityInterface $entity,
        string $relationName,
        string $foreignKey
    ): void {
        $id = $entity->get($foreignKey);
        $user = $this->find($id);
        $entity->set($relationName, $user);
    }

    /**
     * One-To-Many：関連する複数のエンティティを読み込む
     */
    private function fillChildEntities(
        EntityInterface $entity,
        string $relationName,
        string $foreignKey,
        ?string $parentRelationName = null,
        string $orderBy = null,
        string $orderDir = 'ASC',
    ): void {
        $orderBy = $orderBy ?? $this->hydrator->getPrimaryKey();
        $entityClass = $this->hydrator->getEntityClass();
        $sql = "
            SELECT * 
                FROM {$this->hydrator->getTableName()} 
                WHERE {$foreignKey} = :id
                ORDER BY {$orderBy} {$orderDir}";
        $stmt = $this->execute($sql, [':id' => $entity->getId()]);
        if (!$stmt) {
            $entity->set($relationName, []);
            return;
        }
        
        $stmt->setFetchMode(PDO::FETCH_CLASS, $entityClass);
        $list = [];
        while ($childEntity = $stmt->fetch()) {
            // キャッシュに登録
            $id = $childEntity->getId();
            if ($id !== null) {
                $class = get_class($childEntity);
                EntityCache::set($class, $id, $childEntity);
            }

            // 双方向リンクの設定（子 -> 親）
            if ($parentRelationName !== null) {
                $childEntity->set($parentRelationName, $entity);
            }

            $list[] = $childEntity;
        }
        $entity->set($relationName, $list);
    }

    /**
     * One-To-Many (Batch): 複数の親エンティティに対して関連する子エンティティを一括で読み込む
     *
     * @param EntityInterface[] $entities
     * @param string $relationName 親エンティティ側で子リストを保持するプロパティ名（例: 'posts'）
     * @param string $foreignKey 子テーブル側の外部キーカラム名（例: 'user_id'）
     * @param string|null $orderBy
     * @param string $orderDir
     * @param string|null $parentRelationName 子エンティティ側から見た親のリレーション名（例: 'user'）。指定すると双方向リンクを設定する。
     */
    private function fillChildEntitiesBatch(
        array $entities,
        string $relationName,
        string $foreignKey,
        ?string $parentRelationName = null,
        string $orderBy = null,
        string $orderDir = 'ASC',
    ): void {
        if (empty($entities)) {
            return;
        }

        $ids = [];
        $entityMap = [];
        // キャッシュ済みのエンティティが渡されている前提で、$entityMapを作る
        foreach ($entities as $entity) {
            $id = $entity->getId();
            if ($id !== null) {
                $ids[] = $id;
                $entityMap[$id] = $entity;
                // リストを初期化
                $entity->set($relationName, []);
            }
        }

        if (empty($ids)) {
            return;
        }

        $ids = array_unique($ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $orderBy = $orderBy ?? $this->hydrator->getPrimaryKey();
        $entityClass = $this->hydrator->getEntityClass();

        $sql = "
                SELECT * 
                FROM {$this->hydrator->getTableName()} 
                WHERE {$foreignKey} IN ({$placeholders})
                ORDER BY {$orderBy} {$orderDir}
            ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($ids));
        $stmt->setFetchMode(PDO::FETCH_CLASS, $entityClass);

        while ($childEntity = $stmt->fetch()) {
            // キャッシュに登録
            $id = $childEntity->getId();
            if ($id !== null) {
                $class = get_class($childEntity);
                EntityCache::set($class, $id, $childEntity);
            }

            $parentId = $childEntity->get($foreignKey);
            if ($parentId !== null && isset($entityMap[$parentId])) {
                $parent = $entityMap[$parentId];

                // 親 -> 子 のセット
                $currentList = $parent->get($relationName);
                if (!is_array($currentList)) {
                    $currentList = [];
                }
                $currentList[] = $childEntity;
                $parent->set($relationName, $currentList);

                // 子 -> 親 のセット（双方向リンク）
                if ($parentRelationName !== null) {
                    // 子エンティティに親をセットする。
                    // EntityTrait::set を使うことで、setUser() メソッドの存在有無を気にせず透過的に扱える
                    $childEntity->set($parentRelationName, $parent);
                }
            }
        }
    }

    /**
     * データを保存してプライマリキーを返す
     */
    private function insertData(array $data): int|string|bool
    {
        $pKey = $this->hydrator->getPrimaryKey();
        if ($this->hydrator->isPkAutoNumber()) {
            // AutoNumbering に対応。新規ならIDはNULLのはず。
            unset($data[$pKey]);
        }
        $select = [];
        $values = [];
        foreach ($data as $key => $val) {
            $select[] = $key;
            $values[] = ':' . $key;
        }
        // Populate CreatedAt!
        if ($this->hydrator->getCreatedAt() !== null) {
            $data[$this->hydrator->getCreatedAt()] = $this->now->format('Y-m-d H:i:s');
            $select[] = $this->hydrator->getCreatedAt();
            $values[] = ':' . $this->hydrator->getCreatedAt();
        }
        // Populate UpdatedAt!
        if ($this->hydrator->getUpdatedAt() !== null) {
            $data[$this->hydrator->getUpdatedAt()] = $this->now->format('Y-m-d H:i:s');
            $select[] = $this->hydrator->getUpdatedAt();
            $values[] = ':' . $this->hydrator->getUpdatedAt();
        }
        $select = implode(', ', $select);
        $values = implode(', ', $values);

        $sql = "INSERT INTO {$this->hydrator->getTableName()} ({$select}) VALUES ({$values});";
        $stmt = $this->execute($sql, $data);

        if ($stmt) {
            if ($this->hydrator->isPkAutoNumber()) {
                return $this->db->lastInsertId();
            }
            return $data[$pKey] ?? true;
        }
        return false;
    }
}