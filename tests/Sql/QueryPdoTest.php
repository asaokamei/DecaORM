<?php

namespace WScore\DecaORM\Tests\Sql;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Sql\QueryPdo;
use WScore\DecaORM\SqlExecutor;
use WScore\DecaORM\SqlLogger;
use WScore\DecaORM\Tests\Fixtures\ArrayLogger;
use WScore\DecaORM\Tests\Support\DbConnection;
use WScore\DecaORM\Tests\Support\SchemaLoader;

require_once __DIR__ . '/../../vendor/autoload.php';

class QueryPdoTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = DbConnection::get();
        $this->pdo->exec(SchemaLoader::loadTable('drop_all'));
        $this->pdo->exec(SchemaLoader::loadTable('users_query_builder'));
    }

    public function testFetchAllWithTableDefaults(): void
    {
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");

        $query = new QueryPdo($this->pdo, 'users');
        $query->where('name', 'Bob', '!=')->orderBy('name');

        $rows = $query->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('alice@example.com', $rows[0]['email']);
    }

    public function testFetchStream(): void
    {
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('One', 'one@example.com')");
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Two', 'two@example.com')");

        $query = new QueryPdo($this->pdo, 'users');
        $query->orderBy('name');

        $names = [];
        foreach ($query->fetchStream() as $row) {
            $names[] = $row['name'];
        }

        $this->assertSame(['One', 'Two'], $names);
    }

    public function testWithoutDefaultTable(): void
    {
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Solo', 'solo@example.com')");

        $query = new QueryPdo($this->pdo);
        $query->from('users')->select('name')->where('email', 'solo@example.com');

        $rows = $query->fetchAll();

        $this->assertSame([['name' => 'Solo']], $rows);
    }

    public function testNewQueryPreservesDefaultTable(): void
    {
        $query = new QueryPdo($this->pdo, 'users');
        $fresh = $query->newQuery();

        $this->assertSame(
            $query->getSql(),
            $fresh->getSql(),
        );
    }

    public function testExecuteCountQuery(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->pdo->exec("INSERT INTO users (name, email) VALUES ('User {$i}', 'user{$i}@example.com')");
        }

        $query = new QueryPdo($this->pdo, 'users');
        $query->where('name', 'User %', 'LIKE');

        $this->assertSame(5, $query->executeCountQuery());
    }

    public function testPaginate(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->pdo->exec("INSERT INTO users (name, email) VALUES ('User {$i}', 'user{$i}@example.com')");
        }

        $query = new QueryPdo($this->pdo, 'users');
        $query->orderBy('id');

        $result = $query->paginate(2, 5);

        $this->assertCount(5, $result->getItems());
        $this->assertSame(20, $result->getTotalCount());
        $this->assertSame(5, $result->getPerPage());
        $this->assertSame(2, $result->getCurrentPage());
        $this->assertSame(4, $result->getLastPage());
        $this->assertTrue($result->hasPages());
        $this->assertTrue($result->hasMorePages());
        $this->assertSame(6, $result->getFrom());
        $this->assertSame(10, $result->getTo());
        $this->assertSame('User 6', $result->getItems()[0]['name']);
        $this->assertSame('User 10', $result->getItems()[4]['name']);
    }

    public function testPaginateEmptyResult(): void
    {
        $query = new QueryPdo($this->pdo, 'users');

        $result = $query->paginate(1, 5);

        $this->assertSame([], $result->getItems());
        $this->assertSame(0, $result->getTotalCount());
        $this->assertSame(0, $result->getLastPage());
        $this->assertFalse($result->hasPages());
        $this->assertFalse($result->hasMorePages());
        $this->assertSame(0, $result->getFrom());
        $this->assertSame(0, $result->getTo());
    }

    public function testGetPdoStatementUsesAssocFetchMode(): void
    {
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Assoc', 'assoc@example.com')");

        $query = new QueryPdo($this->pdo, 'users');
        $query->where('name', 'Assoc');

        $stmt = $query->getPdoStatement();
        $this->assertNotFalse($stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('Assoc', $row['name']);
        $this->assertArrayNotHasKey(0, $row);
    }

    public function testOptionalSqlExecutor(): void
    {
        $logger = new ArrayLogger();
        $executor = new SqlExecutor(new SqlLogger($logger));

        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Logged', 'logged@example.com')");

        $query = new QueryPdo($this->pdo, 'users', $executor);
        $query->where('name', 'Logged')->fetchAll();

        $this->assertCount(1, $logger->records);
        $this->assertSame('debug', $logger->records[0]['level']);
        $this->assertStringContainsString('FROM', $logger->records[0]['context']['sql']);
    }
}
