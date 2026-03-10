<?php

namespace WScore\DecaORM\Sql;

use PDOStatement;
use WScore\DecaORM\Contacts\RepositoryInterface;

class Delete extends DeleteBuilder
{
    private string $pkColumn;

    public function __construct(private RepositoryInterface $repository)
    {
        $this->table($this->repository->getTableName());
        $this->pkColumn = $this->repository->getPrimaryKeyColumn();
    }

    public function execute(): bool|PDOStatement
    {
        if (!$this->hasWhere()) {
            throw new \RuntimeException('No WHERE condition specified. Use setId() or where() methods.');
        }
        $sql = $this->getSql();
        $data = $this->getParameters();
        return $this->repository->execute($sql, $data);
    }

    /**
     * Set the primary key ID for simple update by ID.
     * This is a convenience method for the common case of updating by ID.
     * Can be combined with where() methods for more complex conditions.
     */
    public function setId(int|string $id): static
    {
        $this->where($this->pkColumn, $id);
        return $this;
    }
}