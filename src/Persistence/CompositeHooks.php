<?php

namespace WScore\DecaORM\Persistence;

use PDOStatement;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryHooksInterface;
use WScore\DecaORM\Sql\Delete;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Sql\Update;

/**
 * Runs multiple hooks in order for each callback (query, insert, update, delete).
 *
 * @phpstan-type HookList list<RepositoryHooksInterface>
 */
final class CompositeHooks implements RepositoryHooksInterface
{
    /** @var HookList */
    private array $hooks;

    /**
     * @param HookList $hooks
     */
    public function __construct(array $hooks)
    {
        $this->hooks = $hooks;
    }

    public function beforeQuery(Query $query): void
    {
        foreach ($this->hooks as $hook) {
            $hook->beforeQuery($query);
        }
    }

    public function beforeInsert(EntityInterface $entity, array &$data): void
    {
        foreach ($this->hooks as $hook) {
            $hook->beforeInsert($entity, $data);
        }
    }

    public function afterInsert(EntityInterface $entity): void
    {
        foreach ($this->hooks as $hook) {
            $hook->afterInsert($entity);
        }
    }

    public function beforeUpdate(Update $update, EntityInterface $entity, array $data, ?array $snapshot): void
    {
        foreach ($this->hooks as $hook) {
            $hook->beforeUpdate($update, $entity, $data, $snapshot);
        }
    }

    public function afterUpdate(EntityInterface $entity, ?PDOStatement $updateStatement = null): void
    {
        foreach ($this->hooks as $hook) {
            $hook->afterUpdate($entity, $updateStatement);
        }
    }

    public function beforeDelete(Delete $delete, EntityInterface $entity): void
    {
        foreach ($this->hooks as $hook) {
            $hook->beforeDelete($delete, $entity);
        }
    }

    public function afterDelete(EntityInterface $entity): void
    {
        foreach ($this->hooks as $hook) {
            $hook->afterDelete($entity);
        }
    }
}
