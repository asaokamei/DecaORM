<?php

namespace WScore\DecaORM\Contracts;

use RuntimeException;

/**
 * Thrown when an optimistic lock precondition is not met (e.g. missing version snapshot, or 0 rows
 * affected because the version column no longer matched).
 *
 * {@see \WScore\DecaORM\Persistence\VersionColumnHooks} can throw this from
 * {@see \WScore\DecaORM\Contracts\RepositoryHooksInterface::afterUpdate()} when
 * {@see \PDOStatement::rowCount()} is zero. Custom hooks may also throw from that callback.
 */
class OptimisticLockException extends RuntimeException
{
}
