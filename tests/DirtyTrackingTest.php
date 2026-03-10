<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\DirtyTracker;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Tests\Support\SpyUserRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;

final class DirtyTrackingTest extends TestCase
{
    private PDO $pdo;
    private SpyUserRepository $repo;

    protected function setUp(): void
    {
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

        EntityCache::clear();

        $container = new TestContainer();
        $container->set(PDO::class, $this->pdo);
        $manager = OrmManager::initialize($container);
        $this->repo = new SpyUserRepository($manager);
        $container->set(SpyUserRepository::class, $this->repo);
    }

    public function testSaveWithoutChangesDoesNotExecuteUpdate(): void
    {
        // insert（この時点でDirtyTracker::takeされる想定）
        $user = $this->repo->create([
            'name' => 'No Change',
            'email' => 'nochange@example.com',
        ]);
        $this->repo->save($user);

        // fetchしてスナップショットを確実に作る（FETCH_CLASSでもfetch()でtake）
        $loaded = $this->repo->findById($user->getId());
        $this->assertInstanceOf(User::class, $loaded);

        $this->repo->executedSql = [];
        $this->repo->save($loaded);

        $updateSql = array_values(array_filter(
            $this->repo->executedSql,
            static fn(string $sql) => stripos($sql, 'UPDATE ') !== false
        ));

        $this->assertCount(0, $updateSql);
    }
    public function testSaveWithoutSnapshotFallsBackToFullUpdate(): void
    {
        // DB上の行を作る
        $this->pdo->exec("INSERT INTO users (user_name, email) VALUES ('A', 'a@example.com')");
        $id = (int) $this->pdo->lastInsertId();

        // new で作ってIDだけ持たせる（DirtyTrackerスナップショット無し）
        $entity = new User();
        $entity->setRaw('id', $id);
        $entity->setRaw('name', 'A2');
        $entity->setRaw('email', 'a@example.com');

        $this->assertFalse(DirtyTracker::has($entity));

        $this->repo->executedSql = [];
        $this->repo->save($entity);

        $updateSql = array_values(array_filter(
            $this->repo->executedSql,
            static fn(string $sql) => stripos($sql, 'UPDATE ') !== false
        ));

        $this->assertGreaterThanOrEqual(1, count($updateSql));

        // 実際に更新されたことも確認
        $stmt = $this->pdo->prepare("SELECT user_name FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('A2', $row['user_name']);
    }

    public function testDeleteForgetsSnapshot(): void
    {
        $user = $this->repo->create([
            'name' => 'To Delete',
            'email' => 'delete@example.com',
        ]);
        $this->repo->save($user);

        $loaded = $this->repo->findById($user->getId());
        $this->assertInstanceOf(User::class, $loaded);

        $this->assertTrue(DirtyTracker::has($loaded));

        $this->repo->delete($loaded);

        $this->assertFalse(DirtyTracker::has($loaded));
    }
}