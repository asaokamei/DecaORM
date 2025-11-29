<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Tests\Users\UserString;

/**
 * PDO::FETCH_CLASSを使った実装のテスト
 */
class RepositoryTraitPDOClassTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec(
            "CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )"
        );
    }

    public function testFetchClassDirect(): void
    {
        // データを挿入
        $this->pdo->exec("INSERT INTO users (name, email, created_at) VALUES ('Test User', 'test@example.com', '2024-01-01 00:00:00')");
        $id = $this->pdo->lastInsertId();

        // PDO::FETCH_CLASSで直接エンティティにマッピング
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, UserString::class);
        $user = $stmt->fetch();

        $this->assertInstanceOf(UserString::class, $user);
        $this->assertEquals((string) $id, $user->user_id);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('2024-01-01 00:00:00', $user->created_at);
    }

    public function testFetchClassAll(): void
    {
        // 複数のデータを挿入
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('User 1', 'user1@example.com')");
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('User 2', 'user2@example.com')");

        // PDO::FETCH_CLASSで全件取得
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY user_id");
        $users = $stmt->fetchAll(PDO::FETCH_CLASS, UserString::class);

        $this->assertCount(2, $users);
        $this->assertInstanceOf(UserString::class, $users[0]);
        $this->assertEquals('User 1', $users[0]->name);
    }

    public function testTypeConversion(): void
    {
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Test', 'test@example.com')");
        $id = $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, UserString::class);
        $user = $stmt->fetch();

        // getId()でintに変換
        $this->assertIsInt($user->getId());
        $this->assertEquals($id, $user->getId());

        // getCreatedAt()でDateTimeImmutableに変換
        $this->assertNull($user->getCreatedAt()); // created_atがnullの場合
    }
}

