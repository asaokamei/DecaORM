<?php

namespace WScore\DecaORM\Tests\Sql;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WScore\DecaORM\Contracts\HydratorInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\Sql\Delete;
use WScore\DecaORM\Sql\DeleteBuilder;
use WScore\DecaORM\Sql\Insert;
use WScore\DecaORM\Sql\Update;
use WScore\DecaORM\Sql\UpdateBuilder;

class UpdateTest extends TestCase
{
    private function repoStub(array &$captured): RepositoryInterface
    {
        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->method('getTableName')->willReturn('users');
        $hydrator->method('getPrimaryKeyColumn')->willReturn('user_id');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');

        $repo = $this->createMock(RepositoryInterface::class);
        $repo->method('getHydrator')->willReturn($hydrator);
        $repo->method('getDb')->willReturn($pdo);
        $repo->method('execute')->willReturnCallback(function (string $sql, array $data) use (&$captured) {
            $captured['sql'] = $sql;
            $captured['data'] = $data;
            return true;
        });

        return $repo;
    }

    public function testExecuteRequiresWhere(): void
    {
        $captured = [];
        $repo = $this->repoStub($captured);

        $update = new Update($repo);
        $update->set('name', 'Alice');

        $this->expectException(RuntimeException::class);
        $update->execute();
    }

    public function testSetIdAddsWhereAndExecuteCallsRepository(): void
    {
        $captured = [];
        $repo = $this->repoStub($captured);

        $update = new Update($repo);
        $update
            ->set('name', 'Alice')
            ->setId(10)
            ->execute();

        $this->assertArrayHasKey('sql', $captured);
        $this->assertArrayHasKey('data', $captured);

        $this->assertStringContainsString('UPDATE `users`', $captured['sql']);
        $this->assertStringContainsString('SET', $captured['sql']);
        $this->assertStringContainsString('WHERE `user_id`', $captured['sql']);

        $this->assertContains('Alice', array_values($captured['data']));
        $this->assertContains(10, array_values($captured['data']));
    }

    public function testDataOmitsPrimaryKeyColumnFromSetClause(): void
    {
        $captured = [];
        $repo = $this->repoStub($captured);

        $update = new Update($repo);
        $update
            ->data([
                'user_id' => 999, // must be ignored for SET (but can appear in WHERE via setId)
                'name' => 'Bob',
            ])
            ->setId(1)
            ->execute();

        // PK column must not be updated in SET clause.
        // (It will still appear in WHERE due to setId().)
        $this->assertStringNotContainsString('SET `user_id` =', $captured['sql']);
        $this->assertStringContainsString('SET `name` =', $captured['sql']);

        $this->assertContains('Bob', array_values($captured['data']));
        $this->assertNotContains(999, array_values($captured['data']));
    }

    public function testSetRawAllowsSqlFunctionsAndBindings(): void
    {
        $captured = [];
        $repo = $this->repoStub($captured);

        $update = new Update($repo);
        $update
            ->setRaw('updated_at = CURRENT_TIMESTAMP')
            ->setRaw('name = UPPER(:set_name)', ['set_name' => 'alice'])
            ->setId(2)
            ->execute();

        $this->assertStringContainsString('updated_at = CURRENT_TIMESTAMP', $captured['sql']);
        $this->assertStringContainsString('name = UPPER(:set_name)', $captured['sql']);
        $this->assertContains('alice', array_values($captured['data']));
        $this->assertContains(2, array_values($captured['data']));
    }

    public function testWhereInExpandsMarkerIntoMultiplePlaceholders(): void
    {
        $captured = [];
        $repo = $this->repoStub($captured);

        $update = new Update($repo);
        $update
            ->set('name', 'Zed')
            ->whereIn('user_id', [1, 2, 3])
            ->execute();

        $this->assertStringContainsString('WHERE', $captured['sql']);
        $this->assertStringContainsString('`user_id` IN (', $captured['sql']);

        // SQLを1回パースして、IN(...) の中身だけ取り出して検証する（改行や空白に強い）
        $this->assertMatchesRegularExpression('/`user_id`\s+IN\s*\((?<in>[^)]*)\)/', $captured['sql']);

        preg_match('/`user_id`\s+IN\s*\((?<in>[^)]*)\)/', $captured['sql'], $m);
        $this->assertArrayHasKey('in', $m);

        $inside = $m['in'];
        $placeholders = array_values(array_filter(array_map('trim', explode(',', $inside))));

        // 3要素に展開されていること
        $this->assertCount(3, $placeholders);

        // それぞれが ":_EXPAND_" で始まるプレースホルダであること
        foreach ($placeholders as $ph) {
            $this->assertStringStartsWith(':_EXPAND_', $ph);
        }

        $this->assertContains('Zed', array_values($captured['data']));
        $this->assertContains(1, array_values($captured['data']));
        $this->assertContains(2, array_values($captured['data']));
        $this->assertContains(3, array_values($captured['data']));
    }

    public function testUpdateBuilderUsesPostgresIdentifierQuote(): void
    {
        $builder = new UpdateBuilder();
        $sql = $builder
            ->setIdentifierQuoteByDriver('pgsql')
            ->table('users u')
            ->set('u.name', 'Alice')
            ->where('u.id', 10)
            ->getSql();

        $this->assertStringContainsString('UPDATE "users" "u"', $sql);
        $this->assertStringContainsString('SET "u"."name" = :', $sql);
        $this->assertStringContainsString('WHERE "u"."id" = :', $sql);
    }

    public function testDeleteBuilderUsesPostgresIdentifierQuote(): void
    {
        $builder = new DeleteBuilder();
        $sql = $builder
            ->setIdentifierQuoteByDriver('pgsql')
            ->table('users u')
            ->where('u.id', 10)
            ->getSql();

        $this->assertStringContainsString('DELETE FROM "users" "u"', $sql);
        $this->assertStringContainsString('WHERE "u"."id" = :', $sql);
    }

    public function testDeleteSetIdUsesQuotedPrimaryKeyOnExecute(): void
    {
        $captured = [];
        $repo = $this->repoStub($captured);

        $delete = new Delete($repo);
        $delete->setId(10)->execute();

        $this->assertStringContainsString('DELETE FROM `users`', $captured['sql']);
        $this->assertStringContainsString('WHERE `user_id` = :', $captured['sql']);
        $this->assertContains(10, array_values($captured['data']));
    }

    public function testInsertUsesQuotedTableAndColumnsOnExecute(): void
    {
        $captured = [];
        $repo = $this->repoStub($captured);

        $insert = new Insert($repo);
        $insert->data([
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ])->execute();

        $this->assertStringContainsString('INSERT INTO `users` (`name`, `email`) VALUES (:name, :email);', $captured['sql']);
        $this->assertSame('Alice', $captured['data']['name']);
        $this->assertSame('alice@example.com', $captured['data']['email']);
    }

    public function testInsertUsesPostgresIdentifierQuote(): void
    {
        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->method('getTableName')->willReturn('users');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('pgsql');

        $repo = $this->createMock(RepositoryInterface::class);
        $repo->method('getHydrator')->willReturn($hydrator);
        $repo->method('getDb')->willReturn($pdo);

        $insert = new Insert($repo);
        $sql = $insert
            ->data(['name' => 'Alice'])
            ->getSql();

        $this->assertStringContainsString('INSERT INTO "users" ("name") VALUES (:name);', $sql);
    }
}