<?php

namespace WScore\DecaORM\Sql;

use Countable;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

class Query extends QueryBuilder implements Countable
{
    /**
     * @var EntityInterface[]
     */
    private array $found;

    public function __construct(private RepositoryInterface $repository)
    {
        $columns = [];
        $table = $this->repository->getTableName();
        $this->from($table);
        foreach ($this->repository->listColumnsToProperties() as $column => $property) {
            $columns[] = "{$table}.{$column} AS {$property}";
        }
        $this->select(...$columns);
    }

    public function newQuery(): static
    {
        return new static($this->repository);
    }

    /**
     * @return EntityInterface[]
     */
    public function getResult(): array
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
    public function getQueryCount(): int
    {
        $query = clone $this;
        $query->select('COUNT(*)');
        $query->limit(null);
        $query->offset(null);
        return (int) $query->getResult()[0]['COUNT(*)'];
    }

    public function getEntities(): array
    {
        return $this->found;
    }

    public function count(): int
    {
        if (!isset($this->found)) {
            $this->getResult();
        }
        return count($this->found);
    }
}