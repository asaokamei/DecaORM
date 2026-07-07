<?php

namespace WScore\DecaORM\Tests\Sql;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Sql\QueryBuilder;
use WScore\DecaORM\Tests\Support\DbConnection;
use WScore\DecaORM\Tests\Support\SchemaLoader;

require_once __DIR__ . '/../../vendor/autoload.php';


class QueryBuilderTest extends TestCase
{
    private PDO $pdo;

    /**
     * Heredocs follow the file's newline bytes; generated SQL uses \n. Normalize so
     * assertions pass under any core.autocrlf / checkout eol.
     */
    private static function normalizeLineEndings(string $sql): string
    {
        return preg_replace('/\R/', "\n", $sql) ?? $sql;
    }

    protected function setUp(): void
    {
        $this->pdo = DbConnection::get();
        $this->pdo->exec(SchemaLoader::loadTable('drop_all'));
        $this->pdo->exec(SchemaLoader::loadTable('users_query_builder'));
    }

    public function testSelectQueryWithParameters()
    {
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Jane Doe', 'jane@example.com')");
        $id = $this->pdo->lastInsertId();

        $builder = new QueryBuilder();
        $builder->select('u.id', 'u.name')
            ->from('users u')
            ->where('u.id', $id)
            ->orderBy('u.id', 'DESC')
            ->limit(3)
            ->offset(0);
        $sql = $builder->getSql();
        $params = $builder->getParameters();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Jane Doe', $user['name']);
        $this->assertEquals($id, $user['id']);
    }

    public function testBasicSelectQuery()
    {
        $builder = new QueryBuilder();

        $user_ids = [101, 102, 103]; // IN句に使用する配列
        $min_age = 25;

        $builder
            ->withRaw("recent_orders AS (SELECT user_id, amount FROM orders WHERE order_date > '2024-01-01')")
            ->select('u.id', 'u.name')
            ->selectRaw('COUNT(ro.user_id) AS order_count')
            ->from('users u')
            ->joinRaw("LEFT JOIN recent_orders ro ON u.id = ro.user_id AND ro.user_id IN (:_EXPAND_user_id)")
            ->whereIn('u.id', $user_ids) // IN句
            ->where('u.status', 'active') // AND句
            ->whereRaw( // OR条件を含むRAW句
                '(u.age > :min_age OR u.score > 90)',
                [':min_age' => $min_age]
            )
            ->limit(3)
            ->offset(2)
            ->orderBy('u.id', 'DESC')
        ->setParameters(['user_id' => $user_ids]);

        $sql = $builder->getSql();
        $params = $builder->getParameters();

        $this->assertNotEmpty($sql);
        $this->assertNotEmpty($params);
        $this->assertCount(8, $params);
        $this->assertEquals(8, substr_count($sql, ':'));
        $expectedSql =<<< END_SQL
WITH recent_orders AS (SELECT user_id, amount FROM orders WHERE order_date > '2024-01-01')
SELECT u.id, u.name, COUNT(ro.user_id) AS order_count 
FROM users u
  LEFT JOIN recent_orders ro ON u.id = ro.user_id AND ro.user_id IN (:_EXPAND_user_id_0, :_EXPAND_user_id_1, :_EXPAND_user_id_2)
WHERE u.id IN (:_EXPAND_u_id_0_0, :_EXPAND_u_id_0_1, :_EXPAND_u_id_0_2)
  AND u.status = :u_status_1
  AND (u.age > :min_age OR u.score > 90)

ORDER BY u.id DESC
LIMIT 3
OFFSET 2

END_SQL;

        $this->assertSame(
            self::normalizeLineEndings($expectedSql),
            self::normalizeLineEndings($sql)
        );
        foreach ($params as $key => $value) {
            $this->assertStringContainsString($key, $sql);
        }
    }

    public function testDistinctIsOmittedByDefault(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder->from('users')->select('id', 'name')->getSql();

        $this->assertStringNotContainsString('DISTINCT', $sql);
        $this->assertStringContainsString('SELECT id, name', $sql);
    }

    public function testDistinctPrependedToSelect(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder->from('users u')->select('u.id', 'u.name')->distinct()->getSql();

        $this->assertStringContainsString('SELECT DISTINCT u.id, u.name', $sql);
        $this->assertStringContainsString('FROM users u', $sql);
    }

    public function testIdentifierQuoteForMysqlDriver(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->setIdentifierQuoteByDriver('mysql')
            ->from('users u')
            ->select('u.id', 'u.name')
            ->where('u.id', 1)
            ->getSql();

        $this->assertStringContainsString('SELECT `u`.`id`, `u`.`name`', $sql);
        $this->assertStringContainsString('FROM `users` `u`', $sql);
        $this->assertStringContainsString('WHERE `u`.`id` = :', $sql);
    }

    public function testIdentifierQuoteForPostgresStyleDriver(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->setIdentifierQuoteByDriver('pgsql')
            ->from('users u')
            ->select('u.id', 'u.name')
            ->where('u.id', 1)
            ->getSql();

        $this->assertStringContainsString('SELECT "u"."id", "u"."name"', $sql);
        $this->assertStringContainsString('FROM "users" "u"', $sql);
        $this->assertStringContainsString('WHERE "u"."id" = :', $sql);
    }

    public function testIdentifierQuoteForSqliteDriver(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->setIdentifierQuoteByDriver('sqlite')
            ->from('users')
            ->select('id')
            ->where('id', 1)
            ->getSql();

        $this->assertStringContainsString('SELECT "id"', $sql);
        $this->assertStringContainsString('FROM "users"', $sql);
        $this->assertStringContainsString('WHERE "id" = :', $sql);
    }

    public function testUnknownDriverDoesNotQuoteIdentifiers(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->setIdentifierQuoteByDriver('sqlsrv')
            ->from('users u')
            ->select('u.id')
            ->where('u.id', 1)
            ->getSql();

        $this->assertStringContainsString('SELECT u.id', $sql);
        $this->assertStringContainsString('FROM users u', $sql);
        $this->assertStringContainsString('WHERE u.id = :', $sql);
        $this->assertStringNotContainsString('`', $sql);
        $this->assertStringNotContainsString('"u"', $sql);
    }

    public function testDistinctFalseRestoresPlainSelect(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder->from('users')->select('id')->distinct()->distinct(false)->getSql();

        $this->assertStringNotContainsString('DISTINCT', $sql);
        $this->assertStringContainsString('SELECT id', $sql);
    }

    public function testDistinctWithWhereAndJoin(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->select('u.id')
            ->from('users u')
            ->joinRaw('INNER JOIN orders o ON o.user_id = u.id')
            ->where('u.status', 'active')
            ->distinct()
            ->getSql();

        $this->assertStringContainsString('SELECT DISTINCT u.id', $sql);
        $this->assertStringContainsString('INNER JOIN orders o', $sql);
        $this->assertStringContainsString('WHERE', $sql);
    }

    public function testGroupByAndHaving(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->select('status')
            ->selectRaw('COUNT(*) AS cnt')
            ->from('users')
            ->where('active', 1)
            ->groupBy('status')
            ->having('cnt', 5, '>')
            ->orderBy('status')
            ->getSql();

        $this->assertStringContainsString('GROUP BY status', $sql);
        $this->assertStringContainsString('HAVING', $sql);
        $this->assertStringContainsString('cnt > :', $sql);
        $this->assertMatchesRegularExpression('/WHERE.*GROUP BY/s', $sql);
        $this->assertMatchesRegularExpression('/GROUP BY.*HAVING/s', $sql);
        $this->assertMatchesRegularExpression('/HAVING.*ORDER BY/s', $sql);

        $params = $builder->getParameters();
        $this->assertContains(5, $params);
        $this->assertContains(1, $params);
    }

    public function testGroupByMultipleCallsAndColumns(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->groupBy('country', 'city')
            ->groupBy('status')
            ->getSql();

        $this->assertStringContainsString('GROUP BY country, city, status', $sql);
    }

    public function testGroupByRawAddsExpression(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->groupBy('status')
            ->groupByRaw('DATE(created_at)')
            ->getSql();

        $this->assertStringContainsString('GROUP BY status, DATE(created_at)', $sql);
    }

    public function testHavingRawMergesBindings(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->groupBy('role')
            ->havingRaw('COUNT(*) >= :min_count', ['min_count' => 10])
            ->getSql();

        $this->assertStringContainsString('HAVING COUNT(*) >= :min_count', $sql);
        $this->assertSame(10, $builder->getParameters()['min_count']);
    }

    public function testForUpdateAppendedAfterLimit(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->where('id', 1)
            ->limit(1)
            ->forUpdate()
            ->getSql();

        $this->assertStringContainsString('LIMIT 1', $sql);
        $this->assertStringEndsWith("FOR UPDATE\n", $sql);
        $this->assertMatchesRegularExpression('/LIMIT\s+1\s*\n\s*FOR UPDATE\s*$/', $sql);
    }

    public function testForUpdateFalseOmitsClause(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder->from('users')->forUpdate(true)->forUpdate(false)->getSql();

        $this->assertStringNotContainsString('FOR UPDATE', $sql);
    }

    public function testForUpdateWithNoWaitAppendsOption(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->forUpdate(noWait: true)
            ->getSql();

        $this->assertStringEndsWith("FOR UPDATE NOWAIT\n", $sql);
    }

    public function testForUpdateWithSkipLockedAppendsOption(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->forUpdate(skipLocked: true)
            ->getSql();

        $this->assertStringEndsWith("FOR UPDATE SKIP LOCKED\n", $sql);
    }

    public function testForUpdateWithNoWaitAndSkipLockedAppendsBothOptions(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->forUpdate(noWait: true, skipLocked: true)
            ->getSql();

        $this->assertStringEndsWith("FOR UPDATE NOWAIT SKIP LOCKED\n", $sql);
    }

    public function testForUpdateFalseClearsNoWaitAndSkipLockedFlags(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->forUpdate(noWait: true, skipLocked: true)
            ->forUpdate(false)
            ->forUpdate()
            ->getSql();

        $this->assertStringEndsWith("FOR UPDATE\n", $sql);
        $this->assertStringNotContainsString('NOWAIT', $sql);
        $this->assertStringNotContainsString('SKIP LOCKED', $sql);
    }

    public function testForUpdateIsOmittedForSqliteDriver(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->setIdentifierQuoteByDriver('sqlite')
            ->from('users')
            ->where('id', 1)
            ->forUpdate(noWait: true, skipLocked: true)
            ->getSql();

        $this->assertStringNotContainsString('FOR UPDATE', $sql);
    }

    public function testOrderByColumnValidatesDirectionAndEscapesIdentifier(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->setIdentifierQuoteByDriver('mysql')
            ->from('users u')
            ->orderBy('u.id', 'desc')
            ->getSql();

        $this->assertStringContainsString('ORDER BY `u`.`id` DESC', $sql);
    }

    public function testOrderByColumnRejectsUnsupportedDirection(): void
    {
        $builder = new QueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $builder
            ->from('users')
            ->orderBy('id', 'DESC NULLS LAST');
    }

    public function testOrderByAccumulatesMultipleCalls(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'ASC')
            ->getSql();

        $this->assertStringContainsString('ORDER BY created_at DESC, id ASC', $sql);
    }

    public function testOrderByWithAlias(): void
    {
        $qb = new QueryBuilder();
        $qb->setIdentifierQuoteByDriver('mysql');
        $qb->from('users');

        $qb->orderBy('user_id', 'desc');
        $this->assertStringContainsString('ORDER BY `user_id` DESC', $qb->getSql());

        $qb->orderBy('my_table.my_column AS col_alias', 'asc');
        $this->assertStringContainsString('`my_table`.`my_column` AS `col_alias` ASC', $qb->getSql());
    }

    public function testClearOrderByResetsPreviousOrderClauses(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->orderBy('created_at', 'DESC')
            ->clearOrderBy()
            ->orderByColumn('id', 'ASC')
            ->getSql();

        $this->assertStringContainsString('ORDER BY id ASC', $sql);
        $this->assertStringNotContainsString('created_at DESC', $sql);
    }

    public function testClearWhereResetsPreviousWhereClauses(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->where('status', 'active')
            ->where('id', 1)
            ->clearWhere()
            ->where('id', 2)
            ->getSql();

        $this->assertStringContainsString('WHERE id = :', $sql);
        $this->assertStringNotContainsString('status', $sql);
        $this->assertContains(2, $builder->getParameters());
    }

    public function testWhereNullConvertsToIsNullWithoutBinding(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->where('opened_at', null)
            ->getSql();

        $this->assertStringContainsString('opened_at IS NULL', $sql);
        $this->assertStringNotContainsString('opened_at = :', $sql);
        $this->assertSame([], $builder->getParameters());
    }

    public function testWhereNullWithNotEqualConvertsToIsNotNullWithoutBinding(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->where('opened_at', null, '!=')
            ->getSql();

        $this->assertStringContainsString('opened_at IS NOT NULL', $sql);
        $this->assertStringNotContainsString('opened_at != :', $sql);
        $this->assertSame([], $builder->getParameters());
    }

    public function testWhereArrayDelegatesToInExpansion(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->where('id', [1, 2, 3])
            ->getSql();

        $this->assertMatchesRegularExpression('/id IN \(:_EXPAND_id_\d+_0, :_EXPAND_id_\d+_1, :_EXPAND_id_\d+_2\)/', $sql);
        $this->assertSame([1, 2, 3], array_values($builder->getParameters()));
    }

    public function testWhereArrayWithUnsupportedOperatorThrows(): void
    {
        $builder = new QueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $builder
            ->from('users')
            ->where('id', [1, 2], '!=');
    }

    public function testWhereNotInDelegatesToNotInExpansion(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->whereNotIn('id', [1, 2, 3])
            ->getSql();

        $this->assertMatchesRegularExpression('/id NOT IN \(:_EXPAND_id_\d+_0, :_EXPAND_id_\d+_1, :_EXPAND_id_\d+_2\)/', $sql);
        $this->assertSame([1, 2, 3], array_values($builder->getParameters()));
    }

    public function testWhereNotInWithEmptyArrayAddsAlwaysTrueCondition(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->whereNotIn('id', [])
            ->getSql();

        $this->assertStringContainsString('WHERE (1 = 1)', $sql);
        $this->assertSame([], $builder->getParameters());
    }

    public function testClearJoinResetsPreviousJoinClauses(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users u')
            ->joinRaw('INNER JOIN orders o ON o.user_id = u.id')
            ->clearJoin()
            ->joinRaw('LEFT JOIN profiles p ON p.user_id = u.id')
            ->getSql();

        $this->assertStringContainsString('LEFT JOIN profiles p', $sql);
        $this->assertStringNotContainsString('INNER JOIN orders', $sql);
    }

    public function testClearGroupByResetsPreviousGroupClauses(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->groupBy('country', 'city')
            ->clearGroupBy()
            ->groupBy('status')
            ->getSql();

        $this->assertStringContainsString('GROUP BY status', $sql);
        $this->assertStringNotContainsString('country', $sql);
        $this->assertStringNotContainsString('city', $sql);
    }

    public function testClearHavingResetsPreviousHavingClauses(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->groupBy('status')
            ->having('cnt', 5, '>')
            ->clearHaving()
            ->havingRaw('COUNT(*) >= :min_count', ['min_count' => 10])
            ->getSql();

        $this->assertStringContainsString('HAVING COUNT(*) >= :min_count', $sql);
        $this->assertStringNotContainsString('cnt >', $sql);
        $this->assertSame(10, $builder->getParameters()['min_count']);
    }

    public function testHavingNullConvertsToIsNullWithoutBinding(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->groupBy('status')
            ->having('closed_at', null)
            ->getSql();

        $this->assertStringContainsString('closed_at IS NULL', $sql);
        $this->assertStringNotContainsString('closed_at = :', $sql);
        $this->assertSame([], $builder->getParameters());
    }

    public function testHavingNullWithNotEqualConvertsToIsNotNullWithoutBinding(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->groupBy('status')
            ->having('closed_at', null, '<>')
            ->getSql();

        $this->assertStringContainsString('closed_at IS NOT NULL', $sql);
        $this->assertStringNotContainsString('closed_at <> :', $sql);
        $this->assertSame([], $builder->getParameters());
    }

    public function testHavingArrayConvertsToInExpansion(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->groupBy('status')
            ->having('status', ['active', 'pending'])
            ->getSql();

        $this->assertMatchesRegularExpression('/status IN \(:_EXPAND_having_status_\d+_0, :_EXPAND_having_status_\d+_1\)/', $sql);
        $this->assertSame(['active', 'pending'], array_values($builder->getParameters()));
    }

    public function testHavingArrayWithUnsupportedOperatorThrows(): void
    {
        $builder = new QueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $builder
            ->from('users')
            ->groupBy('status')
            ->having('status', ['active'], '<>');
    }

    public function testClearParametersResetsParameterBag(): void
    {
        $builder = new QueryBuilder();
        $builder
            ->from('users')
            ->where('id', 1)
            ->getSql();
        $builder->clearParameters()
            ->where('id', 2);

        $this->assertSame(['id_1' => 2], $builder->getParameters());
    }

    public function testOrderByRawAddsExpression(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users')
            ->orderByRaw('FIELD(status, "active", "pending", "disabled")')
            ->getSql();

        $this->assertStringContainsString('ORDER BY FIELD(status, "active", "pending", "disabled")', $sql);
    }

    public function testOrderByRequiresDirectionForRawLikeExpression(): void
    {
        $builder = new QueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $builder
            ->from('users')
            ->orderBy('created_at DESC');
    }

    public function testSelectRawAppendsExpressionAndBindings(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('orders o')
            ->select('o.id')
            ->selectRaw('(SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS line_count', [])
            ->selectRaw('o.total > :min_total AS big', ['min_total' => 100])
            ->getSql();

        $this->assertStringContainsString('o.id, (SELECT COUNT(*)', $sql);
        $this->assertStringContainsString('o.total > :', $sql);
        $this->assertSame(100, $builder->getParameters()['min_total']);
    }

    public function testAddSelectAppendsColumnsWithoutReplacingSelection(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users u')
            ->select('u.id')
            ->addSelect('u.name', 'u.email')
            ->getSql();

        $this->assertStringContainsString('SELECT u.id, u.name, u.email', $sql);
    }

    public function testSelectWithAlias(): void
    {
        $qb = new QueryBuilder();
        $qb->setIdentifierQuoteByDriver('mysql');
        $qb->from('users');

        $qb->select('users.id as user_id', 'name AS user_name');

        $sql = $qb->getSql();
        $this->assertStringContainsString('`users`.`id` AS `user_id`', $sql);
        $this->assertStringContainsString('`name` AS `user_name`', $sql);
    }

    public function testSelectWithTableDotStar(): void
    {
        $qb = new QueryBuilder();
        $qb->setIdentifierQuoteByDriver('mysql');
        $qb->from('users');

        $qb->select('users.*');
        $this->assertStringContainsString('SELECT `users`.*', $qb->getSql());
        $this->assertStringContainsString('FROM `users`', $qb->getSql());
    }

    public function testSelectNoAsAlias(): void
    {
        $qb = new QueryBuilder();
        $qb->setIdentifierQuoteByDriver('mysql');
        $qb->from('users');

        $qb->select('users.id user_id');
        $sql = $qb->getSql();
        $this->assertStringContainsString('`users`.`id` AS `user_id`', $sql);
    }

    public function testSelectReplacesPreviousAddSelectColumns(): void
    {
        $builder = new QueryBuilder();
        $sql = $builder
            ->from('users u')
            ->select('u.id')
            ->addSelect('u.name')
            ->select('u.email')
            ->getSql();

        $this->assertStringContainsString('SELECT u.email', $sql);
        $this->assertStringNotContainsString('u.id,', $sql);
        $this->assertStringNotContainsString('u.name', $sql);
    }

    public function testFromRawWithExpandMarkerInSubquery(): void
    {
        $builder = new QueryBuilder();
        $builder
            ->select('sub.id')
            ->fromRaw('(SELECT id FROM users WHERE id IN (:_EXPAND_uid)) AS sub')
            ->setParameters(['uid' => [10, 20]]);

        $sql = $builder->getSql();
        $this->assertStringContainsString('IN (', $sql);
        $this->assertStringContainsString('_EXPAND_uid_0', $sql);
        $this->assertStringContainsString('_EXPAND_uid_1', $sql);
        $this->assertArrayHasKey('_EXPAND_uid_0', $builder->getParameters());
        $this->assertArrayHasKey('_EXPAND_uid_1', $builder->getParameters());
    }

    public function testWhereRejectsUnsupportedOperator(): void
    {
        $builder = new QueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $builder
            ->from('users')
            ->where('id', 1, '= 1 OR 1=1');
    }

    public function testFromRejectsInvalidTableReference(): void
    {
        $builder = new QueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $builder
            ->from('users u, orders o')
            ->getSql();
    }

    public function testHavingRejectsUnsupportedOperator(): void
    {
        $builder = new QueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $builder
            ->from('users')
            ->groupBy('status')
            ->having('cnt', 1, 'OR 1=1');
    }
}