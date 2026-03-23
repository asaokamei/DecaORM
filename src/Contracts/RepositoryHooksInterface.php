<?php

namespace WScore\DecaORM\Contracts;

use PDOStatement;
use WScore\DecaORM\Sql\Delete;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Sql\Update;

/**
 * Ordered, composable hooks around read queries and write operations.
 *
 * Implementations should be stateless or scoped per repository instance.
 * Combine several hooks with {@see \WScore\DecaORM\Persistence\CompositeHooks}
 * to avoid subclass explosion for tenant scope, soft delete, optimistic locking, etc.
 *
 * Override {@see \WScore\DecaORM\AbstractRepository::insertEntity()},
 * {@see \WScore\DecaORM\AbstractRepository::updateEntity()}, or SQL helpers when presets do not fit.
 */
interface RepositoryHooksInterface
{
    /**
     * Runs after a SELECT {@see Query} is constructed and before it is executed
     * ({@see \WScore\DecaORM\Trait\RepositoryTrait::sqlQuery()}, {@see \WScore\DecaORM\Sql\Query::newQuery()}).
     *
     * Use for global filters (tenant, soft-delete “active only”, etc.).
     */
    public function beforeQuery(Query $query): void;

    /**
     * Runs after INSERT column data is prepared and before SQL is executed.
     *
     * @param array<string, mixed> $data Column name => bound value
     */
    public function beforeInsert(EntityInterface $entity, array &$data): void;

    public function afterInsert(EntityInterface $entity): void;

    /**
     * Runs after the UPDATE builder is prepared (primary key WHERE is already set) and before execute.
     *
     * Use {@see Update::where()} / {@see Update::whereRaw()} for optimistic locking or extra predicates.
     *
     * @param array<string, mixed>               $data     Columns in the SET clause
     * @param array<string, mixed>|null          $snapshot Dirty-tracker snapshot used for the diff, if any
     */
    public function beforeUpdate(Update $update, EntityInterface $entity, array $data, ?array $snapshot): void;

    /**
     * Runs after UPDATE {@code execute()} succeeds and before the dirty-tracker snapshot is refreshed.
     *
     * @param PDOStatement|null $updateStatement The statement returned by the repository {@code execute()} call,
     *                                           or null if the driver returned a non-statement result.
     *                                           Use {@see PDOStatement::rowCount()} inside hooks (e.g. optimistic locking).
     *
     * Use this to align in-memory fields (e.g. version) so {@see \WScore\DecaORM\DirtyTracker::takeEntity()}
     * records the post-persist state.
     */
    public function afterUpdate(EntityInterface $entity, ?PDOStatement $updateStatement = null): void;

    /**
     * Runs after the DELETE builder is prepared (primary key WHERE is already set) and before execute.
     *
     * For soft delete, replace execution by overriding {@see \WScore\DecaORM\AbstractRepository::delete()}
     * or use this hook to add predicates; for UPDATE-based soft delete, throw or skip execute in the hook
     * and perform a custom update from the repository instead.
     */
    public function beforeDelete(Delete $delete, EntityInterface $entity): void;

    public function afterDelete(EntityInterface $entity): void;
}
