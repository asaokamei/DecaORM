<?php

namespace WScore\DecaORM\Tests\Sql;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WScore\DecaORM\Contracts\HydratorInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\Sql\Insert;

class InsertTest extends TestCase
{
    /**
     * @param callable(string, array): (bool|PDOStatement) $executeCallback
     */
    private function repoStub(
        string $driver,
        ?callable $executeCallback = null,
        ?PDO $pdo = null,
    ): RepositoryInterface {
        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->method('getTableName')->willReturn('users');

        $pdo ??= $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn($driver);

        $repo = $this->createMock(RepositoryInterface::class);
        $repo->method('getHydrator')->willReturn($hydrator);
        $repo->method('getDb')->willReturn($pdo);
        if ($executeCallback !== null) {
            $repo->method('execute')->willReturnCallback($executeCallback);
        }

        return $repo;
    }

    public function testReturningAppendsClauseOnPostgresql(): void
    {
        $repo = $this->repoStub('pgsql');

        $sql = (new Insert($repo))
            ->data(['name' => 'Alice'])
            ->returning('user_id')
            ->getSql();

        $this->assertStringContainsString(
            'INSERT INTO "users" ("name") VALUES (:name_0) RETURNING "user_id";',
            $sql
        );
    }

    public function testReturningIsIgnoredOnMysql(): void
    {
        $repo = $this->repoStub('mysql');

        $sql = (new Insert($repo))
            ->data(['name' => 'Alice'])
            ->returning('user_id')
            ->getSql();

        $this->assertStringContainsString(
            'INSERT INTO `users` (`name`) VALUES (:name_0);',
            $sql
        );
        $this->assertStringNotContainsString('RETURNING', $sql);
    }

    public function testLastInsertIdFetchesFromStatementOnPostgresql(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(['user_id' => 42]);

        $repo = $this->repoStub('pgsql', fn (): PDOStatement => $stmt);

        $insert = (new Insert($repo))
            ->data(['name' => 'Alice'])
            ->returning('user_id');
        $insert->execute();

        $this->assertSame(42, $insert->lastInsertId());
    }

    public function testLastInsertIdDelegatesToPdoOnMysql(): void
    {
        $stmt = $this->createMock(PDOStatement::class);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $pdo->method('lastInsertId')->willReturn('7');

        $repo = $this->repoStub('mysql', fn (): PDOStatement => $stmt, $pdo);

        $insert = (new Insert($repo))
            ->data(['name' => 'Alice'])
            ->returning('user_id');
        $insert->execute();

        $this->assertSame('7', $insert->lastInsertId());
    }

    public function testLastInsertIdRequiresExecuteFirst(): void
    {
        $repo = $this->repoStub('mysql');

        $insert = (new Insert($repo))->data(['name' => 'Alice']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insert::execute() must be called before lastInsertId().');
        $insert->lastInsertId();
    }
}
