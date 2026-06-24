<?php

namespace WScore\DecaORM\Sql;

use PDO;
use PDOStatement;
use WScore\DecaORM\SqlExecutor;

/**
 * SELECT query builder that executes via PDO only (no {@see \WScore\DecaORM\Contracts\RepositoryInterface}).
 *
 * Returns associative rows. For entity hydration use {@see Query} via a repository.
 */
class QueryPdo extends QueryBuilder
{
    private ?string $defaultTable;

    public function __construct(
        private PDO $pdo,
        ?string $table = null,
        private ?SqlExecutor $sqlExecutor = null,
    ) {
        $this->defaultTable = $table;
        $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->setIdentifierQuoteByDriver(is_string($driverName) ? $driverName : null);
        if ($table !== null) {
            $this->from($table);
            $this->select("{$table}.*");
        }
    }

    public function newQuery(): static
    {
        return new static($this->pdo, $this->defaultTable, $this->sqlExecutor);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array
    {
        $stmt = $this->getPdoStatement();
        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function fetchStream(): \Generator
    {
        $stmt = $this->getPdoStatement();
        if ($stmt === false) {
            return;
        }

        foreach ($stmt as $row) {
            yield $row;
        }
    }

    public function paginate(int $page, int $perPage = 15): PaginatedResult
    {
        $totalCount = $this->executeCountQuery();

        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);

        return new PaginatedResult($this->fetchAll(), $totalCount, $perPage, $page);
    }

    /**
     * Fetch mode is {@see PDO::FETCH_ASSOC} (see {@see SqlExecutor::execute} when used).
     *
     * @return PDOStatement|false
     */
    public function getPdoStatement(): PDOStatement|false
    {
        return $this->execute($this->getSql(), $this->getParameters());
    }

    public function executeCountQuery(): int
    {
        [$countSql, $params] = $this->toCountSubquery();
        $stmt = $this->execute($countSql, $params);
        if ($stmt === false) {
            return 0;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return (int) ($row['aggregate_count'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(string $sql, array $data = []): PDOStatement|false
    {
        if ($this->sqlExecutor instanceof SqlExecutor) {
            return $this->sqlExecutor->execute($this->pdo, $sql, $data);
        }

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            return false;
        }
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        return $stmt;
    }
}
