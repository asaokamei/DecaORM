<?php

namespace WScore\DecaORM\Trait;

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
use WScore\DecaORM\Attribute\CustomLoader;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\DirtyTracker;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\Relation\LoadBelongsTo;
use WScore\DecaORM\Relation\LoadBelongsToOne;
use WScore\DecaORM\Relation\LoadCustomLoader;
use WScore\DecaORM\Relation\LoadHasMany;
use WScore\DecaORM\Relation\LoadHasOne;
use WScore\DecaORM\Relation\LoadManyToMany;
use WScore\DecaORM\Relation\RelationTrait;
use WScore\DecaORM\RepositoryInterface;
use WScore\DecaORM\Sql\Insert;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Sql\Update;
use WScore\DecaORM\Sql\Delete;

/**
 * @template T of EntityInterface
 */
trait RepositoryTrait
{
    use RelationTrait;
    
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

    public function sqlQuery(): Query
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

    public function getPrimaryKeyColumn(): string
    {
        return $this->hydrator->getPrimaryKeyColumn();
    }

    public function execute(string $sql, array $data): bool|PDOStatement
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
        $list = [];
        foreach ($stmt as $item) {
            $entity = $this->hydrator->hydrate($item);
            DirtyTracker::takeEntity($this->hydrator, $entity);
            $list[] = $entity;
        }
        return $list;
    }

    /**
     * @return T[]
     */
    public function find(int|string $id, string|null $column = null, string|null $orderBy = null): array
    {
        $column = $column ?? $this->hydrator->getPrimaryKeyColumn();
        $orderBy = $orderBy ?? $column;

        $query = $this->sqlQuery()
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
        if (!method_exists($entity, 'getRepositoryClass')) {
            throw new RuntimeException('no repository class defined for entity: ' . $entity);
        }
        $repoName = $entity::getRepositoryClass();
        try {
            return $this->container->get($repoName);
        } catch (NotFoundExceptionInterface) {
            return null;
        } catch (ContainerExceptionInterface $e) {
            throw new RuntimeException('failed to get repository: ' . $repoName, 0, $e);
        }
    }

    /**
     * @return HasMany|HasOne|BelongsTo|BelongsToOne|ManyToMany|null
     */
    public function getRelation(string $propertyName): mixed
    {
        $hydrator = $this->hydrator;
        return $hydrator->getRelation($propertyName);
    }

    /**
     * Insert an entity
     */
    public function insertEntity(EntityInterface $entity): void
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
        $stmt = $this->sqlInsert($data)->execute();

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

        // DirtyTracking: INSERT後の状態をスナップショットとして記録
        DirtyTracker::takeEntity($this->hydrator, $entity);
    }

    public function sqlInsert(array $data): Insert
    {
        $insert = new Insert($this);
        $insert->data($data);

        return $insert;
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
    public function updateEntity(EntityInterface $entity): void
    {
        $id = $entity->getId();
        if ($id === null) {
            throw new RuntimeException('Entity does not have an ID:' . $this->hydrator->getEntityClass());
        }

        $original = DirtyTracker::get($entity);

        // まず「更新対象データ」を決める（スナップショット無しなら全更新、あれば差分のみ）
        $data = DirtyTracker::snapshotFromEntity($this->hydrator, $entity);
        if ($original !== null) {
            $data = DirtyTracker::diffColumns($data, $original);
            if (empty($data)) {
                return; // 差分ゼロ -> SQLを発行しない
            }
        }

        // updated_at を更新（更新が発生する場合のみ）
        $updatedAtProp = $this->hydrator->getUpdatedAt();
        if ($updatedAtProp !== null) {
            $updatedAtCol = $this->hydrator->getColumnNameForProperty($updatedAtProp);
            $entity->set($updatedAtProp, $this->now->format('Y-m-d H:i:s'));
            if ($updatedAtCol !== null && $updatedAtCol !== '') {
                $data[$updatedAtCol] = $entity->get($updatedAtProp);
            }
        }

        $this->sqlUpdate($id, $data)->execute();
        DirtyTracker::takeEntity($this->hydrator, $entity);

    }

    public function sqlUpdate(int|string|null $id = null, array $data = []): Update
    {
        $update = new Update($this);
        if ($id !== null) {
            $update->setId($id);
        }
        return $update->data($data);
    }

    /**
     * Delete an entity
     */
    public function deleteEntity(EntityInterface $entity): void
    {
        $id = $entity->getId();
        if ($id === null) {
            throw new RuntimeException('Entity does not have an ID:' . $this->hydrator->getEntityClass());
        }
        $this->sqlDelete($id)->execute();

        // DirtyTracking: 削除後はスナップショットを破棄
        DirtyTracker::forget($entity);
    }

    public function sqlDelete(int|string|null $id = null): Delete
    {
        $delete = new Delete($this);
        if ($id !== null) {
            $delete->setId($id);
        }
        return $delete;
    }

    /**
     * Fills the specified relation for the given entity or entities.
     * 
     * @param T|T[] $entities
     * @param string $relationName
     * @return EntityCollection The loaded relation entities as a collection.
     */
    public function fill(EntityInterface|array $entities, string $relationName): EntityCollection
    {
        $relation = $this->hydrator->getRelation($relationName);
        $targetRepo = $this->getRepository($relation->targetEntity);

        // Use standard loading (loader is handled inside LoadHasMany/LoadHasOne if specified)
        if ($relation instanceof HasMany) {
            $results = LoadHasMany::load($entities, $relation, $targetRepo, $this);
        } elseif ($relation instanceof HasOne) {
            $results = LoadHasOne::load($entities, $relation, $targetRepo, $this);
        } elseif ($relation instanceof BelongsTo) {
            $results = LoadBelongsTo::load($entities, $relation, $targetRepo);
        } elseif ($relation instanceof BelongsToOne) {
            $results = LoadBelongsToOne::load($entities, $relation, $targetRepo);
        } elseif ($relation instanceof ManyToMany) {
            $results = LoadManyToMany::load($entities, $relation, $this, $targetRepo);
        } elseif ($relation instanceof CustomLoader) {
            $results = LoadCustomLoader::load($entities, $relation, $this);
        } else {
            throw new RuntimeException('unknown relation: ' . get_class($relation));
        }

        return new EntityCollection($targetRepo, $results);
    }

}