<?php

namespace WScore\DecaORM;

use PDO;
use PDOStatement;
use Throwable;

class SqlExecutor
{
    public function __construct(private SqlLogger $logger)
    {
    }

    public function execute(PDO $pdo, string $sql, array $params = []): PDOStatement
    {
        $stmt = $pdo->prepare($sql);
        $start = microtime(true);

        try {
            $stmt->execute($params);
            $durationMs = (microtime(true) - $start) * 1000;
            $this->logger->logSuccess($sql, $params, $durationMs);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);

            return $stmt;
        } catch (Throwable $e) {
            $durationMs = (microtime(true) - $start) * 1000;
            $this->logger->logFailure($sql, $params, $durationMs, $e);
            throw $e;
        }
    }
}
