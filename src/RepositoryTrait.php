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
     * Fetch an entity by PrimaryKey
     *
     * @return ?EntityInterface
     */
    private function fetchEntityById(int|string $id): ?EntityInterface
    {
        $pKey = $this->hydrator->getPrimaryKey();
        $table = $this->hydrator->getTableName();

        return $this->fetch(
            "SELECT * FROM {$table} WHERE {$pKey} = :id",
            ['id' => $id]
        );
    }

    /**
     * Insert an entity
     */
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
     * Update an entity
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
            $data // $data contains the id
        );
    }

    /**
     * Delete an entity
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
     * Many-To-One: Fetch a parent entity
     */
    protected function fillParentEntity(
        EntityInterface $entity,
        string $relationName,
        string $foreignKey
    ): void {
        $id = $entity->get($foreignKey);
        $user = $this->find($id);
        $entity->set($relationName, $user);
    }

    /**
     * One-To-Many: Fetch multiple child entities
     */
    protected function fillChildEntities(
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
            // Register in cache
            $id = $childEntity->getId();
            if ($id !== null) {
                $class = get_class($childEntity);
                EntityCache::set($class, $id, $childEntity);
            }

            // Set bidirectional link (child -> parent)
            if ($parentRelationName !== null) {
                $childEntity->set($parentRelationName, $entity);
            }

            $list[] = $childEntity;
        }
        $entity->set($relationName, $list);
    }

    /**
     * One-To-Many (Batch): Fetch related child entities for multiple parent entities at once
     *
     * @param EntityInterface[] $entities
     * @param string $relationName Property name for the parent entity to hold the child list (e.g. 'posts')
     * @param string $foreignKey Foreign key column name on the child table (e.g. 'user_id')
     * @param string|null $orderBy
     * @param string $orderDir
     * @param string|null $parentRelationName Relation name of the parent from the child entity side (e.g. 'user'). If specified, set bidirectional link.
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
        // Assume cached entities are passed, create $entityMap
        foreach ($entities as $entity) {
            $id = $entity->getId();
            if ($id !== null) {
                $ids[] = $id;
                $entityMap[$id] = $entity;
                // Initialize list
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
            // Register in cache
            $id = $childEntity->getId();
            if ($id !== null) {
                $class = get_class($childEntity);
                EntityCache::set($class, $id, $childEntity);
            }

            $parentId = $childEntity->get($foreignKey);
            if ($parentId !== null && isset($entityMap[$parentId])) {
                $parent = $entityMap[$parentId];

                // Set parent -> child
                $currentList = $parent->get($relationName);
                if (!is_array($currentList)) {
                    $currentList = [];
                }
                $currentList[] = $childEntity;
                $parent->set($relationName, $currentList);

                // Set child -> parent (bidirectional link)
                if ($parentRelationName !== null) {
                    // Set parent to the child entity.
                    // Use EntityTrait::set to handle the existence of the setUser() method transparently.
                    $childEntity->set($parentRelationName, $parent);
                }
            }
        }
    }

    /**
     * Save data and return the primary key
     */
    private function insertData(array $data): int|string|bool
    {
        $pKey = $this->hydrator->getPrimaryKey();
        if ($this->hydrator->isPkAutoNumber()) {
            // Supports AutoNumbering. The ID should be NULL for new records.
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