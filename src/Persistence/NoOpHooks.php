<?php

namespace WScore\DecaORM\Persistence;

use PDOStatement;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryHooksInterface;
use WScore\DecaORM\Sql\Delete;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Sql\Update;

/**
 * Default hooks: no side effects.
 *
 * Not final so applications can override a subset of callbacks without duplicating no-ops.
 */
class NoOpHooks implements RepositoryHooksInterface
{
    public function beforeQuery(Query $query): void
    {
    }

    public function beforeInsert(EntityInterface $entity, array &$data): void
    {
    }

    public function afterInsert(EntityInterface $entity): void
    {
    }

    public function beforeUpdate(Update $update, EntityInterface $entity, array $data, ?array $snapshot): void
    {
    }

    public function afterUpdate(EntityInterface $entity, ?PDOStatement $updateStatement = null): void
    {
    }

    public function beforeDelete(Delete $delete, EntityInterface $entity): void
    {
    }

    public function afterDelete(EntityInterface $entity): void
    {
    }
}
