<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Tests\Users\User;
use WScore\DecaORM\Tests\Users\UserRepository;

// --- Mock Classes for Testing ---

require_once __DIR__ . '/../vendor/autoload.php';


// --- Test Case ---

class DecaOrmTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repo;

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

        $this->repo = new UserRepository($this->pdo);
    }

    public function testCreateAndSaveUser()
    {
        $savedUser = $this->repo->createAndSave([
                                                    'name' => 'John Doe',
                                                    'email' => 'john@example.com'
                                                ]);

        $this->assertNotNull($savedUser->getId());
        $this->assertEquals('John Doe', $savedUser->get('name'));
        $this->assertNotNull($savedUser->get('created_at'));
    }

    public function testFindUser()
    {
        // Setup data
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Jane Doe', 'jane@example.com')");
        $id = $this->pdo->lastInsertId();

        $user = $this->repo->find($id);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($id, $user->getId());
        $this->assertEquals('Jane Doe', $user->get('name'));
        $this->assertEquals('jane@example.com', $user->get('email'));
    }

    public function testUpdateUser()
    {
        $user = new User();
        $user->set('name', 'Test User');
        $user->set('email', 'test@example.com');
        $user = $this->repo->save($user);
        $id = $user->getId();

        // Update
        $user->set('name', 'New Name');
        $this->repo->save($user);

        // Reload from DB to verify persistence
        // Clear cache logic relies on HydratorTrait static property,
        // assuming a new request or clearing cache in a real app.
        // For this test, we simulate fetching fresh data or rely on repository.

        // Directly check DB to ensure an update happened.
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('New Name', $row['name']);
        $this->assertNotNull($row['updated_at']);
    }

    public function testDeleteUser()
    {
        $user = $this->repo->createAndSave([
                                       'name' => 'To Delete',
                                       'email' => 'delete@example.com',
                                   ]);
        $id = $user->getId();

        $this->assertNotNull($user->getId());

        $this->repo->delete($user);

        $this->assertNull($this->repo->find($id));
    }

    public function testIdentityMapCache()
    {
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('Cache Test', 'cache@example.com')");
        $id = $this->pdo->lastInsertId();

        $user1 = $this->repo->find($id);
        $user2 = $this->repo->find($id);

        // HydratorTrait uses a static cache, so the same instance should be returned
        $this->assertSame($user1, $user2);
    }
}