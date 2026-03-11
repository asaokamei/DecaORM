<?php

namespace WScore\DecaORM\Sql;

use Countable;
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
        $this->from($table);
        $this->select("{$table}.*");
    }

    public function newQuery(): static
    {
        return new static($this->repository);
    }

    public function getResult(): EntityCollection
    {
        $sql = $this->getSql();
        $data = $this->getParameters();

        $this->found = $this->repository->fetch($sql, $data);

        return $this->found;
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
        $stmt = $this->repository->execute($query->getSql(), $query->getParameters());
        if (!$stmt) {
            return 0;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        return (int)($row['COUNT(*)'] ?? 0);
    }

    public function getCollection(): EntityCollection
    {
        return $this->getResult();
    }

}