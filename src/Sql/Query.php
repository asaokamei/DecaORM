<?php

namespace WScore\DecaORM\Sql;

use Countable;
use PDOStatement;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

class Query extends QueryBuilder
{
    /**
     * @var EntityCollection
     */
    private EntityCollection $found;

    public function __construct(private RepositoryInterface $repository)
    {
        $table = $this->repository->getHydrator()->getTableName();
        $driverName = $this->repository->getDb()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->setIdentifierQuoteByDriver(is_string($driverName) ? $driverName : null);
        $this->from($table);
        $this->select("{$table}.*");
    }

    public function newQuery(): static
    {
        $query = new static($this->repository);
        $this->repository->applyHooksToQuery($query);

        return $query;
    }

    public function getResult(): EntityCollection
    {
        $sql = $this->getSql();
        $data = $this->getParameters();

        $this->found = $this->repository->fetch($sql, $data);

        return $this->found;
    }

    /**
     * Yields one entity per row like {@see RepositoryInterface::fetchStream()}.
     *
     * @return \Generator<int, EntityInterface>
     */
    public function fetchStream(): \Generator
    {
        yield from $this->repository->fetchStream($this->getSql(), $this->getParameters());
    }

    /**
     * Runs this query and returns the PDO statement without hydrating entities.
     *
     * Fetch mode is {@see \PDO::FETCH_ASSOC} (see {@see \WScore\DecaORM\SqlExecutor::execute}).
     *
     * @return bool|PDOStatement
     */
    public function getPdoStatement(): bool|PDOStatement
    {
        return $this->repository->execute($this->getSql(), $this->getParameters());
    }

    /**
     * Retrieves the total count of records based on the query.
     *
     * @return int The total count of records.
     */
    public function executeCountQuery(): int
    {
        $query = clone $this;
        $query->select('COUNT(*)');
        $query->limit(null);
        $query->offset(null);
        $query->forUpdate(false);
        $stmt = $this->repository->execute($query->getSql(), $query->getParameters());
        if (!$stmt) {
            return 0;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        return (int)($row['COUNT(*)'] ?? 0);
    }
}
