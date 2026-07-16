<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Persistence;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Tests\Support\SpyUserRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;
use WScore\DecaORM\Tests\Support\DbConnection;
use WScore\DecaORM\Tests\Support\SchemaLoader;

final class IsDirtyTest extends TestCase
{
    private PDO $pdo;
    private SpyUserRepository $repo;
    private OrmManager $manager;

    protected function setUp(): void
    {
        $this->pdo = DbConnection::get();
        $this->pdo->exec(SchemaLoader::loadTable('users'));

        $container = new TestContainer();
        $container->set(PDO::class, $this->pdo);
        $this->manager = OrmManager::initialize($container);
        $this->repo = new SpyUserRepository($this->manager);
        $container->set(SpyUserRepository::class, $this->repo);
        $container->set(\WScore\DecaORM\Tests\Fixtures\Relations\UserRepository::class, $this->repo);
    }

    public function testIsDirty(): void
    {
        $user = $this->repo->create([
            'name' => 'Initial Name',
            'email' => 'initial@example.com',
        ]);
        $this->repo->save($user);

        // fetchしてスナップショットを確実に作る
        $loaded = $this->repo->findById($user->getId());
        $this->assertInstanceOf(User::class, $loaded);

        // まだ変更していないので false
        $this->assertFalse($loaded->isDirty());

        // 変更すると true
        $loaded->setRaw('name', 'Changed Name');
        $this->assertTrue($loaded->isDirty());

        // 元に戻すと false
        $loaded->setRaw('name', 'Initial Name');
        $this->assertFalse($loaded->isDirty());
    }

    public function testIsDirtyNewEntity(): void
    {
        $user = new User();
        $user->setOrm($this->manager);
        $user->setRaw('name', 'New User');
        
        // スナップショットがない新規エンティティは dirty と判定される
        $this->assertTrue($user->isDirty());
    }

    public function testIsDirtyAfterSave(): void
    {
        $user = $this->repo->create([
            'name' => 'Name',
            'email' => 'email@example.com',
        ]);
        $this->assertTrue($user->isDirty());

        $this->repo->save($user);
        // save 後は DirtyTracker::take されるので dirty ではなくなる
        $this->assertFalse($user->isDirty());

        $user->setName('New Name');
        $this->assertTrue($user->isDirty());
    }
}
