<?php

namespace WScore\DecaORM\Tests;

use PHPUnit\Framework\TestCase;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;

class PaginationTest extends TestCase
{
    private OrmManager $manager;
    private UserRepository $repo;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY AUTOINCREMENT, user_name TEXT, email TEXT, created_at TEXT, updated_at TEXT)");
        
        // 20件のテストデータを作成
        for ($i = 1; $i <= 20; $i++) {
            $pdo->exec("INSERT INTO users (user_name, email) VALUES ('User {$i}', 'user{$i}@example.com')");
        }

        $container = new TestContainer();
        $container->set(\PDO::class, $pdo);
        $this->manager = OrmManager::initialize($container);
        $this->repo = new UserRepository($this->manager);
        $container->set(UserRepository::class, $this->repo);
    }

    public function testPaginate(): void
    {
        $query = $this->repo->sqlQuery();
        
        // 1ページ目、1ページ5件
        $result = $query->paginate(1, 5);
        
        $this->assertCount(5, $result->getItems());
        $this->assertEquals(20, $result->getTotalCount());
        $this->assertEquals(5, $result->getPerPage());
        $this->assertEquals(1, $result->getCurrentPage());
        $this->assertEquals(4, $result->getLastPage());
        $this->assertTrue($result->hasPages());
        $this->assertTrue($result->hasMorePages());
        $this->assertEquals(1, $result->getFrom());
        $this->assertEquals(5, $result->getTo());
        
        $this->assertEquals('User 1', $result->getItems()[0]->getName());
        $this->assertEquals('User 5', $result->getItems()[4]->getName());
    }

    public function testPaginateSecondPage(): void
    {
        $query = $this->repo->sqlQuery();
        
        // 2ページ目、1ページ5件
        $result = $query->paginate(2, 5);
        
        $this->assertCount(5, $result->getItems());
        $this->assertEquals(20, $result->getTotalCount());
        $this->assertEquals(2, $result->getCurrentPage());
        $this->assertEquals(6, $result->getFrom());
        $this->assertEquals(10, $result->getTo());
        
        $this->assertEquals('User 6', $result->getItems()[0]->getName());
        $this->assertEquals('User 10', $result->getItems()[4]->getName());
    }

    public function testPaginateLastPage(): void
    {
        $query = $this->repo->sqlQuery();
        
        // 最終ページ（4ページ目）、1ページ5件
        $result = $query->paginate(4, 5);
        
        $this->assertCount(5, $result->getItems());
        $this->assertEquals(4, $result->getCurrentPage());
        $this->assertFalse($result->hasMorePages());
        $this->assertEquals(16, $result->getFrom());
        $this->assertEquals(20, $result->getTo());
    }

    public function testPaginatePartialLastPage(): void
    {
        $query = $this->repo->sqlQuery();
        
        // 1ページ3件の場合、最終ページは7ページ目（20 / 3 = 6.66...）
        $result = $query->paginate(7, 3);
        
        $this->assertCount(2, $result->getItems()); // 20 - (6 * 3) = 2
        $this->assertEquals(7, $result->getCurrentPage());
        $this->assertEquals(7, $result->getLastPage());
        $this->assertEquals(19, $result->getFrom());
        $this->assertEquals(20, $result->getTo());
    }

    public function testPaginateEmptyResult(): void
    {
        $pdo = $this->manager->getPDO();
        $pdo->exec("DELETE FROM users");
        
        $query = $this->repo->sqlQuery();
        
        $result = $query->paginate(1, 5);
        
        $this->assertCount(0, $result->getItems());
        $this->assertEquals(0, $result->getTotalCount());
        $this->assertEquals(0, $result->getLastPage());
        $this->assertFalse($result->hasPages());
        $this->assertFalse($result->hasMorePages());
        $this->assertEquals(0, $result->getFrom());
        $this->assertEquals(0, $result->getTo());
    }
}
