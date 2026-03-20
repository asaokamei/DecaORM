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
use WScore\DecaORM\Collection;
use WScore\DecaORM\DirtyTracker;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\HydratorInterface;
use WScore\DecaORM\Relation\LoadBelongsTo;
use WScore\DecaORM\Relation\LoadBelongsToOne;
use WScore\DecaORM\Relation\LoadCustomLoader;
use WScore\DecaORM\Relation\LoadHasMany;
use WScore\DecaORM\Relation\LoadHasOne;
use WScore\DecaORM\Relation\LoadManyToMany;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Sql\Insert;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Sql\Update;
use WScore\DecaORM\Sql\Delete;

/**
 * @template T of EntityInterface
 */
trait RepositoryTrait
{
    protected OrmManager $manager;
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

    public function isNew(EntityInterface $entity): bool
    {
        if ($this->hydrator->isPkAutoNumber()) {
            if ($entity->getId() === null) {
                return true;
            }
            return false;
        }
        return !EntityCache::has($this->hydrator->getEntityClass(), $entity->getId());
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
        return $this->getManager()->getSqlExecutor()->execute($this->db, $sql, $data);
    }

    public function fetch(string $sql, array $data = []): EntityCollection
    {
        $stmt = $this->execute($sql, $data);
        if (!$stmt) {
            return new EntityCollection([], $this);
        }
        $list = [];
        foreach ($stmt as $item) {
            $entity = $this->hydrator->hydrate($item);
            DirtyTracker::takeEntity($this->hydrator, $entity);
            $list[] = $entity;
        }
        return new EntityCollection($list, $this);
    }

    public function find(int|string $id, string|null $column = null, string|null $orderBy = null): EntityCollection
    {
        $column = $column ?? $this->hydrator->getPrimaryKeyColumn();
        $orderBy = $orderBy ?? $column;

        $query = $this->sqlQuery()
            ->where($column, $id)
            ->orderBy($orderBy);
        $sql = $query->getSql();
        $data = $query->getParameters();
        return $this->fetch($sql, $data);
    }

    public function getRepository(string|EntityInterface $entity): RepositoryInterface
    {
        if (!method_exists($entity, 'getRepositoryClass')) {
            throw new RuntimeException('no repository class defined for entity: ' . $entity);
        }
        $repoName = $entity::getRepositoryClass();
        return $this->getManager()->getRepository($repoName);
    }

    protected function getManager(): OrmManager
    {
        if (!isset($this->manager)) {
            throw new RuntimeException('Repository manager is not set. Call setUpRepository() in the repository constructor.');
        }

        return $this->manager;
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
            $entity->setRaw($this->hydrator->getCreatedAt(), $this->now->format('Y-m-d H:i:s'));
        }
        if ($this->hydrator->getUpdatedAt() !== null) {
            $entity->setRaw($this->hydrator->getUpdatedAt(), $this->now->format('Y-m-d H:i:s'));
        }
        $data = $this->hydrator->dehydrate($entity);
        if ($this->hydrator->isPkAutoNumber()) {
            $pkCol = $this->hydrator->getPrimaryKeyColumn();
            if (array_key_exists($pkCol, $data) && $data[$pkCol] === null) {
                unset($data[$pkCol]);
            }
        }
        $stmt = $this->sqlInsert($data)->execute();

        if (!$stmt) {
            throw new RuntimeException('Failed to insert an entity:' . $this->hydrator->getEntityClass());
        }
        if ($this->hydrator->isPkAutoNumber()) {
            $pKey = $this->hydrator->getPrimaryKey();
            $entity->setRaw($pKey, $this->db->lastInsertId());
        }
        EntityCache::cache($entity);

        if ($this->hydrator->isPkAutoNumber()) {
            $this->loadAllForeignKeys($entity);
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

    protected function loadAllForeignKeys(EntityInterface $entity): void
    {
        foreach ($this->hydrator->getRelations() as $relation) {
            if (!($relation instanceof HasMany || $relation instanceof HasOne)) {
                continue;
            }

            $children = $entity->getRaw($relation->propertyName);
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

            // retrieve property names to load: $childBackRefProperty and $childForeignKey.
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
                $child->setRaw($childBackRefProperty, $entity);

                // Set child's foreign key to parent's id when known
                if ($childForeignKey !== null) {
                    $child->setRaw($childForeignKey, $entity->getId());
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
            $entity->setRaw($updatedAtProp, $this->now->format('Y-m-d H:i:s'));
            if ($updatedAtCol !== null && $updatedAtCol !== '') {
                $data[$updatedAtCol] = $entity->getRaw($updatedAtProp);
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
     * Physically deletes an entity from the database.
     */
    public function forceDelete(EntityInterface $entity): void
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
     * Loads the specified relation for the given entity or entities.
     * 
     * @param T|T[] $entities
     * @param string $relationName
     * @return Collection|EntityCollection The loaded relation entities as a collection.
     *         Returns EntityCollection if the result contains EntityInterface instances, Collection otherwise.
     */
    public function load(EntityInterface|array $entities, string $relationName): Collection|EntityCollection
    {
        $relation = $this->hydrator->getRelation($relationName);
        $targetRepo = $relation->targetEntity 
            ? $this->getRepository($relation->targetEntity) 
            : null;

        // Use standard loading (loader is handled inside LoadHasMany/LoadHasOne if specified)
        if ($relation instanceof HasMany) {
            $results = LoadHasMany::load($entities, $relation, $targetRepo, $this);
            return new EntityCollection($results, $targetRepo);
        } elseif ($relation instanceof HasOne) {
            $results = LoadHasOne::load($entities, $relation, $targetRepo, $this);
            return new EntityCollection($results, $targetRepo);
        } elseif ($relation instanceof BelongsTo) {
            $results = LoadBelongsTo::load($entities, $relation, $targetRepo);
            return new EntityCollection($results, $targetRepo);
        } elseif ($relation instanceof BelongsToOne) {
            $results = LoadBelongsToOne::load($entities, $relation, $targetRepo);
            return new EntityCollection($results, $targetRepo);
        } elseif ($relation instanceof ManyToMany) {
            $results = LoadManyToMany::load($entities, $relation, $this, $targetRepo);
            return new EntityCollection($results, $targetRepo);
        } elseif ($relation instanceof CustomLoader) {
            $results = LoadCustomLoader::load($entities, $relation, $this);
            // CustomLoaderの場合、返り値がEntityInterface[]かどうかで判断
            $results = is_array($results) ? $results : [$results];
            if (!empty($results) && $results[0] instanceof EntityInterface) {
                // EntityInterface[]の場合はEntityCollectionを返す
                return new EntityCollection($results, $targetRepo);
            } else {
                // それ以外（計算値など）の場合はCollectionを返す
                return new Collection($results);
            }
        } else {
            throw new RuntimeException('unknown relation: ' . get_class($relation));
        }
    }

}