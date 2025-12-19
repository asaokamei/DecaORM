<?php

namespace WScore\DecaORM\Sql;

use PDOStatement;
use RuntimeException;
use WScore\DecaORM\RepositoryInterface;

class Update extends UpdateBuilder
{
    private string $pkColumn;

    public function __construct(private RepositoryInterface $repository)
    {
        $this->table($this->repository->getTableName());
        $this->pkColumn = $this->repository->getPrimaryKeyColumn();
    }

    /**
     * Repository経由で安全にUPDATEする（WHERE必須）
     */
    public function execute(): bool|PDOStatement
    {
        if (!$this->hasWhere()) {
            throw new RuntimeException('No WHERE condition specified. Use setId() or where() methods.');
        }

        return $this->repository->execute($this->getSql(), $this->getParameters());
    }

    public function setId(int|string $id): static
    {
        $this->where($this->pkColumn, $id);
        return $this;
    }

    /**
     * Repository更新の都合で、PKは更新対象から除外する
     */
    public function data(array $data): static
    {
        unset($data[$this->pkColumn]);
        return parent::data($data);
    }
}