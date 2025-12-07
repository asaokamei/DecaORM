<?php

namespace WScore\DecaORM\Tests\Sql;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Sql\QueryBuilder;

require_once __DIR__ . '/../../vendor/autoload.php';


class QueryBuilderTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        // In-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create table
        $this->pdo->exec(
            "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )"
        );
    }

    public function testSelectQueryWithParameters()
    {
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Jane Doe', 'jane@example.com')");
        $id = $this->pdo->lastInsertId();

        $builder = new QueryBuilder();
        $builder->select('u.id', 'u.name')
            ->from('users u')
            ->where('u.id', $id)
            ->orderBy('u.id DESC')
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
            ->select('u.id', 'u.name', 'COUNT(ro.user_id) AS order_count')
            ->from('users u')
            ->joinRaw("LEFT JOIN recent_orders ro ON u.id = ro.user_id AND ro.user_id IN (:_EXTEND_user_id)")
            ->whereIn('u.id', $user_ids) // IN句
            ->where('u.status', 'active') // AND句
            ->whereRaw( // OR条件を含むRAW句
                '(u.age > :min_age OR u.score > 90)',
                [':min_age' => $min_age]
            )
            ->limit(3)
            ->offset(2)
            ->orderBy('u.id DESC')
        ->setParameters(['user_id' => $user_ids]);

        $sql = $builder->getSql();
        $params = $builder->getParameters();

        $this->assertNotEmpty($sql);
        $this->assertNotEmpty($params);
        $this->assertCount(8, $params);
        $this->assertEquals(8, substr_count($sql, ':'));;
        $expectedSql =<<< END_SQL
WITH recent_orders AS (SELECT user_id, amount FROM orders WHERE order_date > '2024-01-01')
SELECT u.id, u.name, COUNT(ro.user_id) AS order_count 
FROM users u
  LEFT JOIN recent_orders ro ON u.id = ro.user_id AND ro.user_id IN (:_EXTEND_user_id_0, :_EXTEND_user_id_1, :_EXTEND_user_id_2)
WHERE u.id IN (:_EXTEND_u_id_0_0, :_EXTEND_u_id_0_1, :_EXTEND_u_id_0_2)
  AND u.status = :u_status_1
  AND (u.age > :min_age OR u.score > 90)

ORDER BY u.id DESC
LIMIT 3
OFFSET 2

END_SQL;

        $this->assertEquals(explode("\n",$expectedSql), explode(PHP_EOL, $sql));
        foreach ($params as $key => $value) {
            $this->assertStringContainsString($key, $sql);
        }
    }
}