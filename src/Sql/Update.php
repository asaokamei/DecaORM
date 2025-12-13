<?php

namespace WScore\DecaORM\Sql;

use PDOStatement;
use WScore\DecaORM\RepositoryInterface;

class Update
{
    private string $table;
    private array $data = [];
    private array $parameters = [];
    private string $pkColumn;
    private int|string $id;

    public function __construct(private RepositoryInterface $repository)
    {
        $this->table = $this->repository->getTableName();
        $this->pkColumn = $this->repository->getPrimaryKeyColumn();
    }

    public function execute(): bool|PDOStatement
    {
        $sql = $this->getSql();
        $data = $this->getParameters();
        return $this->repository->execute($sql, $data);
    }

    public function setId(int|string $id): static
    {
        $this->id = $id;
        $this->parameters[$this->pkColumn] = $id;
        return $this;
    }

    public function data(array $data): static
    {
        unset($data[$this->pkColumn]);
        $this->data = $data;
        return $this;
    }

    public function getSql(): string
    {
        $values = [];
        foreach ($this->data as $item => $value) {
            $values[] = "{$item} = :{$item}";
        }

        $values = implode(', ', $values);

        if (isset($this->id)) {
            return "
            UPDATE {$this->table} 
                SET {$values} 
                WHERE {$this->pkColumn} = :{$this->pkColumn}";
        }
        throw new \RuntimeException('id is not set when updating data: .');
    }
    public function getParameters(): array
    {
        return array_merge($this->data, $this->parameters);
    }
}