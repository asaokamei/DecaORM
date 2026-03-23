<?php

namespace WScore\DecaORM\Persistence;

use RuntimeException;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Sql\Delete;
use WScore\DecaORM\Sql\Query;

/**
 * Sample: hides logically deleted rows from SELECTs via {@code WHERE deleted_at IS NULL} (or similar).
 *
 * Physical DELETE is not converted to UPDATE here. Override {@see \WScore\DecaORM\AbstractRepository::delete()}
 * (or {@see \WScore\DecaORM\AbstractRepository::forceDelete()}) in your repository to set the deleted
 * column and optionally clear relations — or enable {@see $rejectPhysicalDelete} to fail fast if something
 * still calls the default physical delete path.
 */
class SoftDeleteHooks extends NoOpHooks
{
    /**
     * @param string $deletedAtColumn SQL column used for soft delete (NULL = not deleted)
     * @param bool   $rejectPhysicalDelete If true, {@see beforeDelete()} throws when a DELETE is built
     */
    public function __construct(
        private string $deletedAtColumn = 'deleted_at',
        private bool $rejectPhysicalDelete = false,
        private string $physicalDeleteMessage = 'Physical delete is disabled; use soft delete (UPDATE) instead.',
    ) {
    }

    public function beforeQuery(Query $query): void
    {
        $col = $this->deletedAtColumn;
        $query->whereRaw("{$col} IS NULL", []);
    }

    public function beforeDelete(Delete $delete, EntityInterface $entity): void
    {
        if ($this->rejectPhysicalDelete) {
            throw new RuntimeException($this->physicalDeleteMessage);
        }
    }
}
