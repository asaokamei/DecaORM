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
        $table = $this->repository->getTableName();
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
     * Paginates the query result.
     *
     * @param int $page The current page number (starting from 1).
     * @param int $perPage The number of items per page.
     * @return PaginatedResult
     */
    public function paginate(int $page, int $perPage = 15): PaginatedResult
    {
        $totalCount = $this->executeCountQuery();
        
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        
        $items = $this->getResult();
        
        return new PaginatedResult($items, $totalCount, $perPage, $page);
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
        $query->limit(null);
        $query->offset(null);
        $query->forUpdate(false);

        $countSql = "SELECT COUNT(*) AS aggregate_count FROM (\n{$query->getSql()}\n) AS aggregate_count_query";
        $stmt = $this->repository->execute($countSql, $query->getParameters());
        if (!$stmt) {
            return 0;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        return (int)($row['aggregate_count'] ?? 0);
    }
}
