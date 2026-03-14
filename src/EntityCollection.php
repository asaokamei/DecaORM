<?php

namespace WScore\DecaORM;

use InvalidArgumentException;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

/**
 * Collection specifically for EntityInterface instances.
 * 
 * @template T of EntityInterface
 */
class EntityCollection extends Collection
{
    private array $idMap;

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
        }
    }

    /** Expected entity class (FQCN), or null when collection is empty and no repository was provided. */
    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    /**
     * @param T $item
     */
    public function add(mixed $item): void
    {
        $this->assertEntityMatchesClass($item);
        parent::add($item);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->assertEntityMatchesClass($value);
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
        if ($this->repository === null) {
            throw new InvalidArgumentException('load() requires a repository');
        }
        
        $relatedEntities = [];
        foreach (array_chunk($this->items, $chunkSize) as $chunk) {
            $collection = $this->repository->load($chunk, $propertyName);
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

        $relation = $this->repository->getRelation($propertyName);
        $relatedRepository = $relation->targetEntity 
            ? $this->repository->getRepository($relation->targetEntity) 
            : null;

        return new static($uniqueEntities, $relatedRepository);
    }

    public function save(): static
    {
        if ($this->repository === null) {
            throw new InvalidArgumentException('save() requires a repository');
        }
        
        foreach ($this->items as $entity) {
            $this->repository->save($entity);
        }
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
        if (!isset($this->idMap)) {
            $this->buildIdMap();
        }
        return $this->idMap[$id] ?? null;
    }

    public function hasId(null|int|string $id): bool
    {
        if ($id === null) {
            return false;
        }
        if (!isset($this->idMap)) {
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
        $callback = function ($entity) {
            return $entity->getId();
        };
        return $this->map($callback);
    }

    /**
     * @return array<EntityInterface>
     */
    public function getIdMap(): array
    {
        $map = [];
        foreach ($this->items as $entity) {
            $map[$entity->getId()] = $entity;
        }
        return $map;
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
        return $this;
    }

    /**
     * @param string $foreignKey
     * @return array<array<EntityInterface>>
     */
    public function groupBy(string $foreignKey): array
    {
        $group = [];
        foreach ($this->items as $entity) {
            $key = $entity->getRaw($foreignKey);
            $group[$key][] = $entity;
        }
        return $group;
    }

    private function buildIdMap(): void
    {
        $this->idMap = $this->getIdMap();
    }
}
