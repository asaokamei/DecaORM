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

    /**
     * Get table name for the repository
     * Override this method in subclasses to dynamically change table names
     *
     * @return string
     */
    protected function getTableName(): string
    {
        return $this->hydrator->getTableName();
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
        $list = $stmt->fetchAll();
        foreach ($list as $idx => $entity) {
            $entity = $this->hydrator->hydrate($entity);
            $list[$idx] = EntityCache::cache($entity);
        }

        return $list;
    }

    public function fetch(string $sql, array $data): ?EntityInterface
    {
        $stmt = $this->execute($sql, $data);
        if (!$stmt) {
            return null;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return $this->hydrator->hydrate($row);
    }

    /**
     * Fetch an entity by PrimaryKey
     *
     * @return ?EntityInterface
     */
    private function fetchEntityById(int|string $id): ?EntityInterface
    {
        $pKeyColumn = $this->hydrator->getPrimaryKeyColumn();
        $table = $this->getTableName();

        $row = $this->execute(
            "SELECT * FROM {$table} WHERE {$pKeyColumn} = :id",
            ['id' => $id]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $entity = $this->hydrator->hydrate($row);
        return EntityCache::cache($entity);
    }

    /**
     * Insert an entity
     */
    private function insertEntity(EntityInterface $entity): void
    {
        if ($this->hydrator->isPkAutoNumber()) {
            if ($entity->getId() !== null) {
                throw new \RuntimeException('Entity already has an ID:' . $this->hydrator->getEntityClass());
            }
        } else {
            if ($entity->getId() === null) {
                throw new \RuntimeException('Entity does not have an ID:' . $this->hydrator->getEntityClass());
            }
        }
        if ($this->hydrator->getCreatedAtColumn() !== null) {
            $entity->set($this->hydrator->getCreatedAtColumn(), $this->now->format('Y-m-d H:i:s'));
        }
        if ($this->hydrator->getUpdatedAtColumn() !== null) {
            $entity->set($this->hydrator->getUpdatedAtColumn(), $this->now->format('Y-m-d H:i:s'));
        }
        $data = $this->hydrator->dehydrate($entity);

        $select = [];
        $values = [];
        foreach ($data as $columnName => $value) {
            $select[] = $columnName;
            $values[] = ':' . $columnName;
        }
        $select = implode(', ', $select);
        $values = implode(', ', $values);
        $sql = "INSERT INTO {$this->getTableName()} ({$select}) VALUES ({$values});";
        $stmt = $this->execute($sql, $data);

        if (!$stmt) {
            throw new \RuntimeException('Failed to insert an entity:' . $this->hydrator->getEntityClass());
        }
        if ($this->hydrator->isPkAutoNumber()) {
            $pKey = $this->hydrator->getPrimaryKey();
            $entity->set($pKey, $this->db->lastInsertId());
        }
        EntityCache::cache($entity);
}

    /**
     * Update an entity
     */
    private function updateEntity(EntityInterface $entity): void
    {
        $data = $this->hydrator->dehydrate($entity);
        // Update UpdatedAt!
        if ($this->hydrator->getUpdatedAtColumn() !== null) {
            $entity->set($this->hydrator->getUpdatedAtColumn(), $this->now->format('Y-m-d H:i:s'));
        }
        $values = [];

        // Remove PK!
        $pKeyColumn = $this->hydrator->getPrimaryKeyColumn();
        unset($data[$pKeyColumn]);
        // Remove CreatedAt!
        $createdAtColumn = $this->hydrator->getCreatedAtColumn();
        if ($createdAtColumn !== null) {
            unset($data[$createdAtColumn]);
        }
        foreach ($data as $item => $value) {
            $values[] = "{$item} = :{$item}";
        }

        $values = implode(', ', $values);

        $data[$pKeyColumn] = $entity->getId();
        $this->execute(
            "
            UPDATE {$this->getTableName()} 
                SET {$values} 
                WHERE {$pKeyColumn} = :{$pKeyColumn}",
            $data // $data contains the id
        );
    }

    /**
     * Delete an entity
     */
    private function deleteEntity(EntityInterface $entity): void
    {
        $pKeyColumn = $this->hydrator->getPrimaryKeyColumn();
        $id = $entity->getId();
        $this->execute(
            "
            DELETE FROM {$this->getTableName()} 
                   WHERE {$pKeyColumn} = :id",
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
        $orderBy = $orderBy ?? $this->hydrator->getPrimaryKeyColumn();
        $sql = "
            SELECT * 
                FROM {$this->getTableName()} 
                WHERE {$foreignKey} = :id
                ORDER BY {$orderBy} {$orderDir}";
        $list = $this->fetchAll($sql, [':id' => $entity->getId()]);
        if (!$list || empty($list) === 0) {
            $entity->set($relationName, []);
            return;
        }
        
        foreach ($list as $childEntity) {
            // Register in cache
            $childEntity = EntityCache::cache($childEntity);

            // Set bidirectional link (child -> parent)
            if ($parentRelationName !== null) {
                $childEntity->set($parentRelationName, $entity);
            }
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
                FROM {$this->getTableName()} 
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

            // foreignKey is a column name, convert to property name for entity access
            $propertyName = $this->hydrator->getPropertyNameForColumn($foreignKey);
            $parentId = $childEntity->get($propertyName);
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
}