<?php

namespace WScore\DecaORM;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;
use WScore\DecaORM\Contracts\SqlParamMaskerInterface;
use WScore\DecaORM\Sql\NoOpSqlParamMasker;

class SqlLogger
{
    public function __construct(
        private LoggerInterface $logger,
        private int $slowQueryThresholdMs = 100,
        ?SqlParamMaskerInterface $paramMasker = null,
    ) {
        $this->paramMasker = $paramMasker ?? new NoOpSqlParamMasker();
    }

    private SqlParamMaskerInterface $paramMasker;

    public function logSuccess(string $sql, array $params, float $durationMs): void
    {
        $this->logger->log(
            $this->resolveSuccessLevel($durationMs),
            'SQL executed.',
            [
                'sql' => $sql,
                'params' => $this->paramMasker->mask($params),
                'duration_ms' => $durationMs,
            ]
        );
    }

    public function logFailure(string $sql, array $params, float $durationMs, Throwable $e): void
    {
        $this->logger->error(
            'SQL execution failed.',
            [
                'sql' => $sql,
                'params' => $this->paramMasker->mask($params),
                'duration_ms' => $durationMs,
                'exception' => $e,
            ]
        );
    }

    private function resolveSuccessLevel(float $durationMs): string
    {
        if ($durationMs >= $this->slowQueryThresholdMs) {
            return LogLevel::WARNING;
        }

        return LogLevel::DEBUG;
    }
}
