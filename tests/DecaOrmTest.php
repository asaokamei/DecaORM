<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\RepositoryManager;
use WScore\DecaORM\Tests\Fixtures\ArrayLogger;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;

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

        $this->pdo->exec(
            "CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )"
        );

        // Clear cache before each test
        \WScore\DecaORM\EntityCache::clear();

        $container = new TestContainer();
        $container->set(PDO::class, $this->pdo);
        $manager = RepositoryManager::initialize($container);
        $this->repo = new UserRepository($manager);
        $container->set(UserRepository::class, $this->repo);
    }

    public function testCreateAndSaveUser()
    {
        $savedUser = $this->repo->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        $this->repo->save($savedUser);

        $this->assertNotNull($savedUser->getId());
        $this->assertEquals('John Doe', $savedUser->getName());
        $this->assertNotNull($savedUser->getRegisteredAt());
        $this->assertNotNull($savedUser->getUpdatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $savedUser->getRegisteredAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $savedUser->getUpdatedAt());
    }

    public function testFindUser()
    {
        // Setup data
        $this->pdo->exec("INSERT INTO users (user_name, email) VALUES ('Jane Doe', 'jane@example.com')");
        $id = $this->pdo->lastInsertId();

        $user = $this->repo->findById($id);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($id, $user->getId());
        $this->assertEquals('Jane Doe', $user->getName());
        $this->assertEquals('jane@example.com', $user->getEmail());

        EntityCache::clear();
        $stmt = $this->repo->execute('SELECT * FROM users WHERE user_id = ?', [$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Jane Doe', $row['user_name']);

        EntityCache::clear();
        $entities = $this->repo->fetch('SELECT * FROM users WHERE user_id = ?', [$id]);
        $this->assertCount(1, $entities);
        /** @var User $entity */
        $entity = $entities[0];
        $this->assertEquals('Jane Doe', $entity->getName());
        $this->assertEquals($id, $entity->getId());
    }

    public function testUpdateUser()
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail('test@example.com');
        $this->assertNull($user->getId());
        $this->repo->save($user);
        $this->assertNotNull($user->getId());
        $id = $user->getId();

        // Update
        $user->setName('New Name');
        $this->repo->save($user);

        // Reload from DB to verify persistence
        // Clear cache logic relies on HydratorTrait static property,
        // assuming a new request or clearing cache in a real app.
        // For this test, we simulate fetching fresh data or rely on repository.

        // Directly check DB to ensure an update happened.
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('New Name', $row['user_name']);
        $this->assertNotNull($row['updated_at']);
    }

    public function testDeleteUser()
    {
        $user = $this->repo->create([
            'name' => 'To Delete',
            'email' => 'delete@example.com',
        ]);
        $this->repo->save($user);
        $id = $user->getId();

        $this->assertNotNull($user->getId());

        $this->repo->delete($user);

        $this->assertNull($this->repo->findById($id));
    }

    public function testIdentityMapCache()
    {
        $this->pdo->exec("INSERT INTO users (user_name, email) VALUES ('Cache Test', 'cache@example.com')");
        $id = $this->pdo->lastInsertId();

        $user1 = $this->repo->findById($id);
        $user2 = $this->repo->findById($id);

        // HydratorTrait uses a static cache, so the same instance should be returned
        $this->assertSame($user1, $user2);
    }

    public function testExecuteLogsSqlThroughRepositoryManager(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )"
        );

        $container = new TestContainer();
        $container->set(PDO::class, $pdo);
        $logger = new ArrayLogger();
        $manager = RepositoryManager::initialize($container)
            ->setLogger($logger)
            ->setSlowQueryThresholdMs(0);
        $repo = new UserRepository($manager);

        $repo->execute(
            'INSERT INTO users (user_name, email) VALUES (:name, :email)',
            ['name' => 'Logger Test', 'email' => 'logger@example.com']
        );

        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertSame('SQL executed.', $logger->records[0]['message']);
        $this->assertSame(
            'INSERT INTO users (user_name, email) VALUES (:name, :email)',
            $logger->records[0]['context']['sql']
        );
        $this->assertSame('Logger Test', $logger->records[0]['context']['params']['name']);
        $this->assertArrayHasKey('duration_ms', $logger->records[0]['context']);
    }

    public function testExecuteWorksWithoutConfiguredLogger(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )"
        );

        $container = new TestContainer();
        $container->set(PDO::class, $pdo);
        $manager = RepositoryManager::initialize($container)
            ->setLogger(null);
        $repo = new UserRepository($manager);

        $stmt = $repo->execute(
            'INSERT INTO users (user_name, email) VALUES (:name, :email)',
            ['name' => 'No Logger', 'email' => 'nologger@example.com']
        );

        $this->assertSame(1, $stmt->rowCount());
        $this->assertSame('1', $pdo->lastInsertId());
    }
}