<?php

namespace WScore\DecaORM\Sql;

use Countable;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contacts\EntityInterface;
use WScore\DecaORM\Contacts\RepositoryInterface;

class Query extends QueryBuilder
{
    /**
     * @var \WScore\DecaORM\Contacts\EntityInterface[]
     */
    private array $found;

    public function __construct(private RepositoryInterface $repository)
    {
        $table = $this->repository->getTableName();
        $this->from($table);
        $this->select("{$table}.*");
    }

    public function newQuery(): static
    {
        return new static($this->repository);
    }

    /**
     * @return \WScore\DecaORM\Contacts\EntityInterface[]
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
    public function executeCountQuery(): int
    {
        $query = clone $this;
        $query->select('COUNT(*)');
        $query->limit(null);
        $query->offset(null);
        return (int) $query->getResult()[0]['COUNT(*)'];
    }

    public function getCollection(): EntityCollection
    {
        $entities = $this->getResult();
        return new EntityCollection($entities, $this->repository);
    }

}