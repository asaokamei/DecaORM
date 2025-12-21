<?php

namespace WScore\DecaORM;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * @template T of EntityInterface
 */
class EntityCollection implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @param RepositoryInterface $repository
     * @param array|EntityInterface[]|T[] $entities
     */
    public function __construct(
        private RepositoryInterface $repository,
        private array $entities = []
    ) {
    }

    public function fill(string $propertyName, int $chunkSize = 100): static
    {
        $relatedEntities = [];
        foreach (array_chunk($this->entities, $chunkSize) as $chunk) {
            $collection = $this->repository->fill($chunk, $propertyName);
            $relatedEntities = array_merge($relatedEntities, $collection->getEntities());
        }


        $uniqueEntity = [];
        foreach ($relatedEntities as $entity) {
            if (isset($uniqueEntity[$entity->getId()])) continue;
            $uniqueEntity[$entity->getId()] = $entity;
        }
        $uniqueEntities = array_values($uniqueEntity);

        $relation = $this->repository->getRelation($propertyName);
        $relatedRepository = $this->repository->getRepository($relation->targetEntity);

        return new static($relatedRepository, $uniqueEntities);
    }

    public function save(): static
    {
        foreach ($this->entities as $entity) {
            $this->repository->save($entity);
        }
        return $this;
    }

    /**
     * @return array|EntityInterface[]|T[]
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * @param string $propertyName
     * @return array|string[]
     */
    public function getValues(string $propertyName): array
    {
        $callback = function ($entity) use ($propertyName) {
            return $entity->get($propertyName);
        };
        return $this->map($callback);
    }

    /**
     * @return array|int[]|string[]
     */
    public function getIds(): array
    {
        $callback = function ($entity) {
            return $entity->getId();
        };
        return $this->map($callback);

    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->entities);
    }

    public function each(callable $callback): static
    {
        array_walk($this->entities, $callback);
        return $this;
    }

    public function filter(callable $callback): static
    {
        $entities = array_filter($this->entities, $callback);
        return new static($this->repository, $entities);
    }

    /**
     * @param callable|string|string[] $callback
     * @return $this
     */
    public function sort(callable|array|string $callback): EntityCollection
    {
        if (is_string($callback)) {
            $callback = [$callback];
        }
        if (!is_callable($callback) && is_array($callback)) {
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
        usort($this->entities, $callback);
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
        foreach (array_chunk($this->entities, $size, $preserveKeys) as $chunk) {
            $chunks[] = new static($this->repository, $chunk);
        }
        return $chunks;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->entities);
    }

    public function count(): int
    {
        return count($this->entities);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->entities[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->entities[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->entities[] = $value;
        } else {
            $this->entities[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->entities[$offset]);
    }
}