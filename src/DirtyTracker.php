<?php

namespace WScore\DecaORM;

use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\HydratorInterface;

/**
 * Column snapshot tracking for dirty detection (per {@see OrmManager} instance).
 */
final class DirtyTracker
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $snapshots = [];

    public function takeEntity(HydratorInterface $hydrator, EntityInterface $entity): void
    {
        $this->take($entity, $this->snapshotFromEntity($hydrator, $entity));
    }

    public function take(EntityInterface $entity, array $data): void
    {
        $this->snapshots[\spl_object_id($entity)] = $data;
    }

    public function has(EntityInterface $entity): bool
    {
        return \array_key_exists(\spl_object_id($entity), $this->snapshots);
    }

    public function get(EntityInterface $entity): ?array
    {
        $key = \spl_object_id($entity);
        return $this->snapshots[$key] ?? null;
    }

    public function forget(EntityInterface $entity): void
    {
        unset($this->snapshots[\spl_object_id($entity)]);
    }

    /**
     * @return array<string, mixed> column => value
     */
    public function snapshotFromEntity(HydratorInterface $hydrator, EntityInterface $entity): array
    {
        $data = [];
        $pkColumn = $hydrator->getPrimaryKeyColumn();

        foreach ($hydrator->listProperties() as $property) {
            $column = $hydrator->getColumnNameForProperty($property);
            if ($column === null || $column === '') {
                continue;
            }
            if ($column === $pkColumn) {
                continue;
            }
            $data[$column] = $entity->getRaw($property);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $original
     * @return array<string, mixed>
     */
    public function diffColumns(array $current, array $original): array
    {
        $changes = [];
        foreach ($current as $col => $curVal) {
            $cur = $this->normalizeForCompare($curVal);
            $org = $this->normalizeForCompare($original[$col] ?? null);
            if ($cur !== $org) {
                $changes[$col] = $curVal;
            }
        }
        return $changes;
    }

    private function normalizeForCompare(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }
        return \json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[[non-scalar]]';
    }
}
