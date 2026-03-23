<?php

namespace WScore\DecaORM\Persistence;

use PDOStatement;
use RuntimeException;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\OptimisticLockException;
use WScore\DecaORM\Sql\Update;

/**
 * Sample optimistic locking: {@code WHERE version = :expected} and {@code SET version = version + 1}.
 *
 * Requirements:
 *
 * - Map the version field as a normal column so it appears in the dirty-tracker snapshot.
 * - Do not include the version column in the UPDATE diff; this hook updates it in SQL only.
 *   If the version column appears in {@code $data}, {@see beforeUpdate()} throws.
 * - When {@see $strictSnapshot} is true (default), updates without a snapshot or without the
 *   version column throw {@see OptimisticLockException}.
 *
 * When {@see $throwOnStaleRow} is true (default), {@see afterUpdate()} throws
 * {@see OptimisticLockException} if {@see PDOStatement::rowCount()} is {@code 0} while a version
 * predicate was applied (stale snapshot / concurrent writer). If {@see PDOStatement::rowCount()} is
 * unavailable ({@code -1}) or the statement is null, the check is skipped.
 */
class VersionColumnHooks extends NoOpHooks
{
    private ?int $expectedVersionForLastUpdate = null;

    /**
     * @param string      $versionColumn    SQL column name (e.g. {@code version})
     * @param string|null $versionProperty  Entity property for {@see EntityInterface::setRaw()} / {@see getRaw()};
     *                                      pass {@code null} to skip syncing the entity from hooks
     * @param bool        $throwOnStaleRow  If true, {@see afterUpdate()} throws when affected rows are 0
     */
    public function __construct(
        private string $versionColumn = 'version',
        private ?string $versionProperty = 'version',
        private bool $strictSnapshot = true,
        private bool $throwOnStaleRow = true,
    ) {
    }

    public function beforeInsert(EntityInterface $entity, array &$data): void
    {
        if (!array_key_exists($this->versionColumn, $data)) {
            $data[$this->versionColumn] = 1;
        }
        if ($this->versionProperty !== null) {
            $entity->setRaw($this->versionProperty, $data[$this->versionColumn]);
        }
    }

    public function beforeUpdate(Update $update, EntityInterface $entity, array $data, ?array $snapshot): void
    {
        $this->expectedVersionForLastUpdate = null;

        if (array_key_exists($this->versionColumn, $data)) {
            throw new RuntimeException(
                'Version column must not be part of the UPDATE SET payload; '
                . 'VersionColumnHooks updates it with version = version + 1.'
            );
        }

        if ($snapshot === null || !array_key_exists($this->versionColumn, $snapshot)) {
            if ($this->strictSnapshot) {
                throw new OptimisticLockException(
                    'Missing dirty-tracker snapshot or version column for optimistic locking.'
                );
            }

            return;
        }

        $expected = $snapshot[$this->versionColumn];
        $this->expectedVersionForLastUpdate = (int) $expected;

        $update->where($this->versionColumn, $expected);
        $col = $this->versionColumn;
        $update->setRaw("{$col} = {$col} + 1");
    }

    public function afterUpdate(EntityInterface $entity, ?PDOStatement $updateStatement = null): void
    {
        if ($this->expectedVersionForLastUpdate !== null && $this->throwOnStaleRow && $updateStatement !== null) {
            $n = $updateStatement->rowCount();
            if ($n >= 0 && $n === 0) {
                $this->expectedVersionForLastUpdate = null;
                throw new OptimisticLockException(
                    'Concurrent update detected: no row matched the version precondition (0 rows affected).'
                );
            }
        }

        if ($this->versionProperty === null || $this->expectedVersionForLastUpdate === null) {
            return;
        }
        $entity->setRaw($this->versionProperty, $this->expectedVersionForLastUpdate + 1);
        $this->expectedVersionForLastUpdate = null;
    }
}
