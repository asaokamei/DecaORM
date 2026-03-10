<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\EntityHandler;
use WScore\DecaORM\RepositoryManager;
use WScore\DecaORM\Tests\Fixtures\EntityActions\ActionUser;
use WScore\DecaORM\Tests\Fixtures\EntityActions\ActionUserRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;
use WScore\DecaORM\Tests\Support\SpyActionUserRepository;

final class EntityActionsTraitTest extends TestCase
{
    private PDO $pdo;
    private TestContainer $container;
    private RepositoryManager $manager;
    private SpyActionUserRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec(
            "CREATE TABLE action_users (
                user_id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_name TEXT
            )"
        );

        EntityCache::clear();

        $this->container = new TestContainer();
        $this->container->set(PDO::class, $this->pdo);
        $this->manager = RepositoryManager::initialize($this->container);

        $this->repo = new SpyActionUserRepository($this->manager);
        $this->container->set(ActionUserRepository::class, $this->repo);
    }

    public function testSaveAndDeleteDelegateToRepository(): void
    {
        $user = new ActionUser();
        $user->fill(['name' => 'A']);

        $user->save();
        $this->assertNotNull($user->getId());

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM action_users")->fetchColumn();
        $this->assertSame(1, $count);

        $user->delete();
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM action_users")->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testLoadDelegatesToRepository(): void
    {
        $user = new ActionUser();
        $user->fill(['name' => 'A']);
        $user->save();

        $result = $user->load('anything');

        $this->assertSame($user, $this->repo->loadEntity);
        $this->assertSame('anything', $this->repo->loadRelationName);
        $this->assertSame($result, $this->repo->loadReturn);
    }

    public function testGetHandlerAndReplicate(): void
    {
        $user = new ActionUser();
        $user->fill(['name' => 'A']);
        $user->save();

        $handler = $user->getHandler();
        $this->assertInstanceOf(EntityHandler::class, $handler);
        $this->assertSame($user, $handler->getEntity());

        $replicatedHandler = $user->replicate();
        $this->assertInstanceOf(EntityHandler::class, $replicatedHandler);
        $this->assertNotSame($user, $replicatedHandler->getEntity());
        $this->assertNull($replicatedHandler->getEntity()->getId());
    }
}

