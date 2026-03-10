<?php

namespace WScore\DecaORM;

use WScore\DecaORM\Contacts\EntityInterface;
use WScore\DecaORM\Contacts\HydratorInterface;

final class DirtyTracker
{
    /**
     * @var array<int, array<string, mixed>> snapshots by spl_object_id
     */
    private static array $snapshots = [];

    /**
     * 現在のエンティティ状態からスナップショットを作り、保存する
     */
    public static function takeEntity(HydratorInterface $hydrator, EntityInterface $entity): void
    {
        self::take($entity, self::snapshotFromEntity($hydrator, $entity));
    }

    public static function take(EntityInterface $entity, array $data): void
    {
        self::$snapshots[\spl_object_id($entity)] = $data;
    }

    public static function has(EntityInterface $entity): bool
    {
        return \array_key_exists(\spl_object_id($entity), self::$snapshots);
    }

    public static function get(EntityInterface $entity): ?array
    {
        $key = \spl_object_id($entity);
        return self::$snapshots[$key] ?? null;
    }

    public static function forget(EntityInterface $entity): void
    {
        unset(self::$snapshots[\spl_object_id($entity)]);
    }

    /**
     * DirtyTracking用: リレーションなどを除外し、カラムのみスナップショット化する（PKは除外）
     *
     * @return array<string, mixed> column => value
     */
    public static function snapshotFromEntity(HydratorInterface $hydrator, EntityInterface $entity): array
    {
        $data = [];
        $pkColumn = $hydrator->getPrimaryKeyColumn();

        foreach ($hydrator->listProperties() as $property) {
            $column = $hydrator->getColumnNameForProperty($property);
            if ($column === null || $column === '') {
                continue;
            }
            if ($column === $pkColumn) {
                continue; // PKはUPDATE対象にしない
            }
            $data[$column] = $entity->get($property);
        }

        return $data;
    }

    /**
     * DirtyTracking: current - original の差分を返す（差分ゼロなら空配列）
     *
     * @param array<string,mixed> $current
     * @param array<string,mixed> $original
     * @return array<string,mixed>
     */
    public static function diffColumns(array $current, array $original): array
    {
        $changes = [];
        foreach ($current as $col => $curVal) {
            $cur = self::normalizeForCompare($curVal);
            $org = self::normalizeForCompare($original[$col] ?? null);
            if ($cur !== $org) {
                $changes[$col] = $curVal;
            }
        }
        return $changes;
    }

    /**
     * DirtyTracking用: 文字列寄せで比較するための正規化
     */
    private static function normalizeForCompare(mixed $value): ?string
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
        // 配列・オブジェクトは追跡対象外にしたいが、万一入った場合は「変化した」扱いに寄せる
        return \json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[[non-scalar]]';
    }
}