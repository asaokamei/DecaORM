<?php

namespace WScore\DecaORM;

use InvalidArgumentException;

/**
 * Collection specifically for EntityInterface instances.
 * 
 * @template T of EntityInterface
 */
class EntityCollection extends Collection
{
    private array $idMap;
    
    /**
     * @param array<EntityInterface>|T[] $entities
     * @param ?RepositoryInterface $repository
     */
    public function __construct(
        array $entities = [],
        private ?RepositoryInterface $repository = null
    ) {
        parent::__construct($entities);
    }

    public function fill(string $propertyName, int $chunkSize = 100): static
    {
        if ($this->repository === null) {
            throw new InvalidArgumentException('fill() requires a repository');
        }
        
        $relatedEntities = [];
        foreach (array_chunk($this->items, $chunkSize) as $chunk) {
            $collection = $this->repository->fill($chunk, $propertyName);
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

    public function findById(int|string $id): ?EntityInterface
    {
        if (!isset($this->idMap)) {
            $this->buildIdMap();
        }
        return $this->idMap[$id] ?? null;
    }

    public function hasId(int|string $id): bool
    {
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
            return $entity->get($propertyName);
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
                    $diff = $a->get($key) <=> $b->get($key);
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
     * @param int $size
     * @param bool $preserveKeys
     * @return static[]
     */
    public function chunk(int $size = 100, bool $preserveKeys = false): array
    {
        $chunks = [];
        foreach (array_chunk($this->items, $size, $preserveKeys) as $chunk) {
            $chunks[] = new static($chunk, $this->repository);
        }
        return $chunks;
    }

    /**
     * @param string $foreignKey
     * @return array<array<EntityInterface>>
     */
    public function groupBy(string $foreignKey): array
    {
        $group = [];
        foreach ($this->items as $entity) {
            $key = $entity->get($foreignKey);
            $group[$key][] = $entity;
        }
        return $group;
    }

    private function buildIdMap(): void
    {
        $this->idMap = $this->getIdMap();
    }
}
