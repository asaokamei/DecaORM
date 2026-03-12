<?php

namespace WScore\DecaORM;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * Generic collection class for any array values.
 * 
 * @template T
 */
class Collection implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @param array<T> $items
     */
    public function __construct(
        protected array $items = []
    ) {
    }

    /**
     * @param T $item
     */
    public function add(mixed $item): void
    {
        $this->items[] = $item;
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * @return array<T>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return array<T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function each(callable $callback): static
    {
        array_walk($this->items, $callback);
        return $this;
    }

    public function filter(callable $callback): static
    {
        $filtered = array_filter($this->items, $callback);
        return new static($filtered);
    }

    /**
     * @param callable $callback
     * @return static
     */
    public function sort(callable $callback): static
    {
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
            $chunks[] = new static($chunk);
        }
        return $chunks;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}

