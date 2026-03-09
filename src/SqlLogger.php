<?php

namespace WScore\DecaORM;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

class SqlLogger
{
    public function __construct(
        private LoggerInterface $logger,
        private int $slowQueryThresholdMs = 100,
    ) {
    }

    public function logSuccess(string $sql, array $params, float $durationMs): void
    {
        $this->logger->log(
            $this->resolveSuccessLevel($durationMs),
            'SQL executed.',
            [
                'sql' => $sql,
                'params' => $params,
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
                'params' => $params,
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
