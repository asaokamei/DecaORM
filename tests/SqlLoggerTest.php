<?php

namespace WScore\DecaORM\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WScore\DecaORM\SqlLogger;
use WScore\DecaORM\Tests\Fixtures\ArrayLogger;

class SqlLoggerTest extends TestCase
{
    public function testLogsDebugForFastQuery(): void
    {
        $logger = new ArrayLogger();
        $sqlLogger = new SqlLogger($logger, 100);

        $sqlLogger->logSuccess('SELECT 1', [], 10);

        $this->assertCount(1, $logger->records);
        $this->assertSame('debug', $logger->records[0]['level']);
    }

    public function testLogsWarningForSlowQuery(): void
    {
        $logger = new ArrayLogger();
        $sqlLogger = new SqlLogger($logger, 100);

        $sqlLogger->logSuccess('SELECT sleep(1)', [], 150);

        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
    }

    public function testLogsErrorForFailedQuery(): void
    {
        $logger = new ArrayLogger();
        $sqlLogger = new SqlLogger($logger, 100);
        $exception = new RuntimeException('boom');

        $sqlLogger->logFailure('SELECT broken', ['id' => 1], 12, $exception);

        $this->assertCount(1, $logger->records);
        $this->assertSame('error', $logger->records[0]['level']);
        $this->assertSame($exception, $logger->records[0]['context']['exception']);
    }
}
