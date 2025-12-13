<?php

namespace WScore\DecaORM;

use DateTimeInterface;
use PDO;
use PDOStatement;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Traversable;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Sql\Query;

/**
 * @template T of EntityInterface
 */
trait RepositoryTrait
{
    protected ?ContainerInterface $container;
    protected PDO $db;
    protected HydratorInterface $hydrator;
    protected DateTimeInterface $now;

    public function getDb(): PDO
    {
        return $this->db;
    }

    public function getHydrator(): HydratorInterface
    {
        return $this->hydrator;
    }

    public function query(): Query
    {
        return new Query($this);
    }

    /**
     * Get the table name for the repository
     * Override this method in subclasses to dynamically change table names
     */
    public function getTableName(): string
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
     * @return T[]
     */
    public function fetch(string $sql, array $data = []): array
    {
        $stmt = $this->execute($sql, $data);
        if (!$stmt) {
            return [];
        }
        $list = $stmt->fetchAll(PDO::FETCH_CLASS, $this->hydrator->getEntityClass());
        foreach ($list as $idx => $entity) {
            $list[$idx] = EntityCache::cache($entity);
        }

        return $list;
    }

    /**
     * @return T[]
     */
    public function find(int|string $id, string $column = null, string $orderBy = null): array
    {
        $column = $column ?? $this->hydrator->getPrimaryKeyColumn();
        $orderBy = $orderBy ?? $column;

        $query = $this->query()
            ->where($column, $id)
            ->orderBy($orderBy);
        $sql = $query->getSql();
        $data = $query->getParameters();
        return $this->fetch($sql, $data) ?? [];
    }

    public function listColumnsToProperties(): array
    {
        $list = [];
        foreach ($this->hydrator->listProperties() as $property) {
            $column = $this->hydrator->getColumnNameForProperty($property);
            $list[$column] = $property;
        }

        return $list;
    }

    public function getRepository(string|EntityInterface $entity): ?RepositoryInterface
    {
        $repoName = $entity::getRepositoryClass();
        if (!$this->container->has($repoName)) {
            throw new RuntimeException('no such repository: ' . $repoName);
        }
        /** @var RepositoryInterface $childRepo */
        try {
            return $this->container->get($repoName);
        } catch (NotFoundExceptionInterface) {
            return null;
        } catch (ContainerExceptionInterface $e) {
            throw new RuntimeException('failed to get repository: ' . $repoName, 0, $e);
        }
    }

    /**
     * @return HasMany|HasOne|BelongsTo|BelongsToOne|null
     */
    public function getRelation(string $propertyName): mixed
    {
        $hydrator = $this->hydrator;
        return $hydrator->getRelation($propertyName);
    }

    /**
     * Insert an entity
     */
    private function insertEntity(EntityInterface $entity): void
    {
        if ($this->hydrator->isPkAutoNumber()) {
            if ($entity->getId() !== null) {
                throw new RuntimeException('Entity already has an ID:' . $this->hydrator->getEntityClass());
            }
        } else {
            if ($entity->getId() === null) {
                throw new RuntimeException('Entity does not have an ID:' . $this->hydrator->getEntityClass());
            }
        }
        if ($this->hydrator->getCreatedAt() !== null) {
            $entity->set($this->hydrator->getCreatedAt(), $this->now->format('Y-m-d H:i:s'));
        }
        if ($this->hydrator->getUpdatedAt() !== null) {
            $entity->set($this->hydrator->getUpdatedAt(), $this->now->format('Y-m-d H:i:s'));
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
            throw new RuntimeException('Failed to insert an entity:' . $this->hydrator->getEntityClass());
        }
        if ($this->hydrator->isPkAutoNumber()) {
            $pKey = $this->hydrator->getPrimaryKey();
            $entity->set($pKey, $this->db->lastInsertId());
        }
        EntityCache::cache($entity);

        if ($this->hydrator->isPkAutoNumber()) {
            $this->fillAllForeignKeys($entity);
        }
    }

    protected function fillAllForeignKeys(EntityInterface $entity): void
    {
        foreach ($this->hydrator->getRelations() as $relation) {
            if (!($relation instanceof HasMany || $relation instanceof HasOne)) {
                continue;
            }

            $children = $entity->get($relation->propertyName);
            if ($children === null) {
                continue;
            }
            if ($relation instanceof HasOne) {
                // Normalize the single child to array
                $children = $children ? [$children] : [];
            } elseif (!is_array($children)) {
                // Convert Traversable to array; ignore invalid types
                if ($children instanceof Traversable) {
                    $children = iterator_to_array($children);
                } else {
                    $children = [];
                }
            }
            if (empty($children)) {
                continue;
            }

            // retrieve property names to fill: $childBackRefProperty and $childForeignKey.
            $childRepo = $this->getRepository($relation->targetEntity);
            $childRel = $childRepo->getRelation($relation->mappedBy);
            if (!$childRel instanceof BelongsTo && !$childRel instanceof BelongsToOne) {
                continue;
            }
            $childBackRefProperty = $childRel->propertyName ?? $relation->mappedBy; // fallback to mappedBy
            $childForeignKey = $childRel->foreignKey ?? null;

            foreach ($children as $child) {
                if (!$child instanceof EntityInterface) {
                    continue; // ignore invalid child
                }
                $child->set($childBackRefProperty, $entity);

                // Set child's foreign key to parent's id when known
                if ($childForeignKey !== null) {
                    $child->set($childForeignKey, $entity->getId());
                }
            }
        }
    }

    /**
     * Update an entity
     */
    private function updateEntity(EntityInterface $entity): void
    {
        $data = $this->hydrator->dehydrate($entity);
        // Update UpdatedAt!
        if ($this->hydrator->getUpdatedAt() !== null) {
            $entity->set($this->hydrator->getUpdatedAt(), $this->now->format('Y-m-d H:i:s'));
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
     * Fills the specified relation for the given entity.
     */
    public function fill(EntityInterface $entity, string $relationName): void
    {
        $relation = $this->hydrator->getRelation($relationName);
        $targetRepo = $this->getRepository($relation->targetEntity);
        $targetRepo->load($entity, $relation);
    }

    /**
     * Loads the specified relation for the given entity.
     *
     * @param EntityInterface $entity The entity for which the relation is to be loaded.
     * @param HasMany|HasOne|BelongsTo|BelongsToOne $relation The relation to be loaded; must be an instance of a supported relation type.
     * @return void
     */
    public function load(EntityInterface $entity, mixed $relation): void
    {
        if ($relation instanceof HasMany) {
            $this->loadHasMany($entity, $relation);
        } elseif ($relation instanceof HasOne) {
            $this->loadHasOne($entity, $relation);
        } elseif ($relation instanceof BelongsTo) {
            $this->loadBelongsTo($entity, $relation);
        } elseif ($relation instanceof BelongsToOne) {
            $this->loadBelongsTo($entity, $relation);
        } else {
            throw new RuntimeException('unknown relation: ' . get_class($relation));
        }
    }

    protected function loadHasMany(EntityInterface $parentEntity, HasMany $parentRelation): void
    {
        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;
        $childRelation = $this->getRelation($parentRelation->mappedBy);

        // Find posts by foreign key
        $children = $this->find($parentEntity->getId(), $childRelation->foreignKey, $parentRelation->orderBy);

        if (empty($children)) {
            $parentEntity->set($parentProperty, []);
            return;
        }

        // Set the bidirectional link (post -> user)
        foreach ($children as $child) {
            $child->set($childProperty, $parentEntity);
        }

        $parentEntity->set($parentProperty, $children);
    }

    protected function loadHasOne(EntityInterface $parentEntity, HasOne $parentRelation): void
    {
        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;
        $childRelation = $this->getRelation($parentRelation->mappedBy);

        // Find posts by foreign key
        $children = $this->find($parentEntity->getId(), $childRelation->foreignKey);

        if (empty($children)) {
            $parentEntity->set($parentProperty, null);
            return;
        }
        if (count($children) > 1) {
            throw new RuntimeException('HasOne relation must have only one child.');
        }
        $child = $children[0];
        $child->set($childProperty, $parentEntity);
        $parentEntity->set($parentProperty, $child);
    }

    protected function loadBelongsTo(EntityInterface $childEntity, mixed $childRelation): void
    {
        $parentId = $childEntity->get($childRelation->foreignKey);
        $parentEntity = $this->findById($parentId);
        $childEntity->set($childRelation->propertyName, $parentEntity);
    }
}