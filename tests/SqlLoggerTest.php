<?php

namespace WScore\DecaORM\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WScore\DecaORM\Sql\KeyBasedSqlParamMasker;
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

    public function testMasksSensitiveParamsForSuccessLog(): void
    {
        $logger = new ArrayLogger();
        $masker = new KeyBasedSqlParamMasker(['password', 'token']);
        $sqlLogger = new SqlLogger($logger, 100, $masker);

        $sqlLogger->logSuccess('SELECT 1', ['password' => 'secret', 'token' => 'abc', 'id' => 1], 10);

        $this->assertSame('***', $logger->records[0]['context']['params']['password']);
        $this->assertSame('***', $logger->records[0]['context']['params']['token']);
        $this->assertSame(1, $logger->records[0]['context']['params']['id']);
    }

    public function testMasksSensitiveParamsForFailureLogWithNestedArray(): void
    {
        $logger = new ArrayLogger();
        $masker = new KeyBasedSqlParamMasker(['password']);
        $sqlLogger = new SqlLogger($logger, 100, $masker);
        $exception = new RuntimeException('boom');

        $sqlLogger->logFailure('SELECT broken', ['meta' => ['Password' => 'secret']], 12, $exception);

        $this->assertSame('***', $logger->records[0]['context']['params']['meta']['Password']);
    }

    public function testMasksSensitiveParamsWithRealWorldPlaceholders(): void
    {
        $logger = new ArrayLogger();
        $masker = new KeyBasedSqlParamMasker(['password']);
        $sqlLogger = new SqlLogger($logger, 100, $masker);

        $sqlLogger->logSuccess('UPDATE users SET password = :set_password_0', ['set_password_0' => 'secret'], 10);

        $this->assertSame('***', $logger->records[0]['context']['params']['set_password_0']);
    }
}
