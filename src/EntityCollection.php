<?php

namespace WScore\DecaORM;

use InvalidArgumentException;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\OrmManager;

/**
 * Collection specifically for EntityInterface instances.
 * Repository is intentionally omitted from serialization (e.g. PDO cannot be restored).
 *
 * @template T of EntityInterface
 */
class EntityCollection extends Collection
{
    /**
     * Map of entity ID => entity.
     * Null means "not built / invalidated" and will be rebuilt on demand.
     *
     * @var ?array<int|string, EntityInterface>
     */
    private ?array $idMap = null;

    /** Expected entity class (FQCN); null when collection is empty and no repository was provided. */
    private ?string $entityClass = null;

    /**
     * @param array<EntityInterface>|T[] $entities All elements must be the same entity class.
     * @param ?RepositoryInterface $repository Optional; when provided, entity class is taken from its hydrator.
     */
    public function __construct(
        array $entities = [],
        private ?RepositoryInterface $repository = null
    ) {
        $this->resolveEntityClass($entities, $repository);
        $this->checkAllEntitiesSameClass($entities);
        parent::__construct($entities);
    }

    /**
     * @param array<EntityInterface>|T[] $items
     */
    protected function newCollection(array $items): static
    {
        return new static($items, $this->repository);
    }

    private function resolveEntityClass(array $entities, ?RepositoryInterface $repository): void
    {
        if ($repository instanceof RepositoryInterface) {
            $this->entityClass = $repository->getHydrator()->getEntityClass();
            return;
        }
        if ($entities === []) {
            $this->entityClass = null;
            return;
        }
        $first = $entities[0];
        if ($first instanceof EntityInterface) {
            $this->entityClass = get_class($first);
            return;
        }
        throw new InvalidArgumentException('resolveEntityClass() requires a repository or entities');
    }

    private function checkAllEntitiesSameClass(array $entities): void
    {
        $idMap = [];
        foreach ($entities as $index => $entity) {
            if (!$entity instanceof EntityInterface) {
                throw new InvalidArgumentException(
                    'EntityCollection accepts only ' . EntityInterface::class . ' instances, got ' . (is_object($entity) ? get_class($entity) : gettype($entity)) . ' at index ' . $index
                );
            }
            if ($this->entityClass !== null && get_class($entity) !== $this->entityClass) {
                throw new InvalidArgumentException(
                    'EntityCollection requires all entities to be the same class. Expected ' . $this->entityClass . ', got ' . get_class($entity) . ' at index ' . $index
                );
            }

            $id = $entity->getId();
            if ($id !== null && !isset($idMap[$id])) {
                $idMap[$id] = $entity;
            }
        }
        $this->idMap = $idMap;
    }

    /** Expected entity class (FQCN), or null when collection is empty and no repository was provided. */
    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    /**
     * Returns the repository, resolving from OrmManager when null (e.g. after unserialize).
     */
    private function resolveRepository(): RepositoryInterface
    {
        if ($this->repository !== null) {
            return $this->repository;
        }
        if ($this->entityClass === null) {
            throw new InvalidArgumentException('Cannot resolve repository: collection is empty and no repository was provided.');
        }
        $repoClass = $this->entityClass::getRepositoryClass();
        $this->repository = OrmManager::getRepository($repoClass);
        return $this->repository;
    }

    /**
     * Serialize without repository (PDO etc. cannot be restored on unserialize).
     *
     * @return array{items: array<EntityInterface>, entityClass: ?string}
     */
    public function __serialize(): array
    {
        return [
            'items' => $this->items,
            'entityClass' => $this->entityClass,
        ];
    }

    /**
     * Restore from serialized data. Repository is left null (use resolve when needed).
     *
     * @param array{items: array<EntityInterface>, entityClass: ?string} $data
     */
    public function __unserialize(array $data): void
    {
        $this->entityClass = $data['entityClass'] ?? null;
        $this->repository = null;
        $this->items = $data['items'] ?? [];
        $this->idMap = null;
    }

    /**
     * @param T $item
     */
    public function add(mixed $item): void
    {
        $this->assertEntityMatchesClass($item);
        $this->idMap = null;
        parent::add($item);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->assertEntityMatchesClass($value);
        $this->idMap = null;
        parent::offsetSet($offset, $value);
    }

    /** @throws InvalidArgumentException */
    private function assertEntityMatchesClass(mixed $entity): void
    {
        if ($this->entityClass === null && $entity instanceof EntityInterface) {
            $this->entityClass = get_class($entity);
        }
        if (!$entity instanceof EntityInterface) {
            throw new InvalidArgumentException(
                'EntityCollection accepts only ' . EntityInterface::class . ' instances, got ' . (is_object($entity) ? get_class($entity) : gettype($entity))
            );
        }
        if ($this->entityClass !== null && get_class($entity) !== $this->entityClass) {
            throw new InvalidArgumentException(
                'EntityCollection requires all entities to be the same class. Expected ' . $this->entityClass . ', got ' . get_class($entity)
            );
        }
    }

    public function load(string $propertyName, int $chunkSize = 100): static
    {
        $repository = $this->resolveRepository();

        $relatedEntities = [];
        foreach (array_chunk($this->items, $chunkSize) as $chunk) {
            $collection = $repository->load($this->newCollection($chunk), $propertyName);
            $relatedEntities = array_merge($relatedEntities, $collection->getEntities());
        }

        $uniqueEntity = [];
        foreach ($relatedEntities as $entity) {
            if (!($entity instanceof EntityInterface)) {
                continue;
            }
            if (isset($uniqueEntity[$entity->getId()])) continue;
            $uniqueEntity[$entity->getId()] = $entity;
        }
        $uniqueEntities = array_values($uniqueEntity);

        $relation = $repository->getRelation($propertyName);
        $relatedRepository = $relation->targetEntity
            ? $repository->getRepository($relation->targetEntity)
            : null;

        return new static($uniqueEntities, $relatedRepository);
    }

    public function save(): static
    {
        $repository = $this->resolveRepository();
        foreach ($this->items as $entity) {
            $repository->save($entity);
        }
        // IDs may be assigned/changed during save; invalidate the map.
        $this->idMap = null;
        return $this;
    }

    /**
     * @return array<EntityInterface>|T[]
     */
    public function getEntities(): array
    {
        return $this->items;
    }

    public function delEntity(EntityInterface $entity): void
    {
        $id = $entity->getId();
        $this->items = array_filter($this->items, function ($item) use ($entity, $id) {
            if ($item === $entity) {
                return false;
            }
            if ($id !== null && $item instanceof EntityInterface && $item->getId() === $id) {
                return false;
            }
            return true;
        });
        $this->idMap = null;
    }

    /**
     * Returns whether the collection contains the given entity (by reference or by same ID).
     */
    public function hasEntity(EntityInterface $entity): bool
    {
        $id = $entity->getId();
        foreach ($this->items as $item) {
            if ($item === $entity) {
                return true;
            }
            if ($id !== null && $item instanceof EntityInterface && $item->getId() === $id) {
                return true;
            }
        }
        return false;
    }

    public function findById(int|string $id): ?EntityInterface
    {
        if ($this->idMap === null) {
            $this->buildIdMap();
        }
        return $this->idMap[$id] ?? null;
    }

    public function hasId(null|int|string $id): bool
    {
        if ($id === null) {
            return false;
        }
        if ($this->idMap === null) {
            $this->buildIdMap();
        }
        return isset($this->idMap[$id]);
    }

    /**
     * @param string $propertyName
     * @return array<string>
     */
    public function getValues(string $propertyName): array
    {
        $callback = function ($entity) use ($propertyName) {
            return $entity->getRaw($propertyName);
        };
        return $this->map($callback);
    }

    /**
     * @return array<int|string>
     */
    public function getIds(): array
    {
        if ($this->idMap === null) {
            $this->buildIdMap();
        }
        return array_keys($this->idMap);
    }

    /**
     * @return array<EntityInterface>
     */
    public function getIdMap(): array
    {
        if ($this->idMap === null) {
            $this->buildIdMap();
        }
        return $this->idMap;
    }

    /**
     * Like {@see groupBy} but omits entities whose raw value at $propertyName is null.
     *
     * @return array<int|string, static>
     */
    public function groupByNonNullProperty(string $propertyName): array
    {
        $buckets = [];
        foreach ($this->items as $entity) {
            $key = $entity->getRaw($propertyName);
            if ($key === null) {
                continue;
            }
            $buckets[$key][] = $entity;
        }
        $group = [];
        foreach ($buckets as $key => $entities) {
            $group[$key] = $this->newCollection($entities);
        }
        return $group;
    }

    /**
     * @param callable|string|string[] $callback
     * @return $this
     */
    public function sort(callable|array|string $callback): static
    {
        if (is_string($callback)) {
            $callback = [$callback];
        }
        if (!is_callable($callback) && is_array($callback)) {
            // Property-based sorting
            $callback = function ($a, $b) use ($callback) {
                foreach ($callback as $key) {
                    $diff = $a->getRaw($key) <=> $b->getRaw($key);
                    if ($diff) return $diff;
                }
                return 0;
            };
        }
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('invalid callback.');
        }
        usort($this->items, $callback);
        $this->idMap = null;
        return $this;
    }

    /**
     * @param string $foreignKey
     * @return array<int|string, static> Keyed by raw property value; each value is a sub-collection with the same repository as this collection.
     */
    public function groupBy(string $foreignKey): array
    {
        $buckets = [];
        foreach ($this->items as $entity) {
            $key = $entity->getRaw($foreignKey);
            $buckets[$key][] = $entity;
        }
        $group = [];
        foreach ($buckets as $key => $entities) {
            $group[$key] = $this->newCollection($entities);
        }
        return $group;
    }

    private function buildIdMap(): void
    {
        $map = [];
        foreach ($this->items as $entity) {
            $id = $entity->getId();
            if ($id === null) {
                continue;
            }
            if (!isset($map[$id])) {
                $map[$id] = $entity;
            }
        }
        $this->idMap = $map;
    }
}
