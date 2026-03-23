<?php

namespace WScore\DecaORM\Tests\Persistence;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Contracts\OptimisticLockException;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Persistence\CompositeHooks;
use WScore\DecaORM\Persistence\SoftDeleteHooks;
use WScore\DecaORM\Persistence\TenantScopeHooks;
use WScore\DecaORM\Persistence\VersionColumnHooks;
use WScore\DecaORM\Tests\Fixtures\Persistence\HookItem;
use WScore\DecaORM\Tests\Fixtures\Persistence\HookItemRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;
use WScore\DecaORM\Tests\Support\DbConnection;

/**
 * Exercises sample hooks through {@see HookItemRepository} and a real DB (SQLite / MySQL / PostgreSQL in CI).
 *
 * Complements {@see SamplePersistenceHooksTest} (isolated SQL/exception checks) and
 * {@see \WScore\DecaORM\Tests\PersistenceHooksTest} (generic hook wiring on User).
 */
class PersistenceHooksIntegrationTest extends TestCase
{
    private static function hookItemsTableDdl(): string
    {
        $type = getenv('DB_TYPE') ?: 'sqlite';

        return match ($type) {
            'sqlite' => 'CREATE TABLE hook_items (
                hook_item_id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                version INTEGER NOT NULL DEFAULT 1,
                tenant_id INTEGER NOT NULL DEFAULT 0,
                deleted_at TEXT NULL
            )',
            'postgresql' => 'CREATE TABLE hook_items (
                hook_item_id SERIAL PRIMARY KEY,
                name TEXT NOT NULL,
                version INTEGER NOT NULL DEFAULT 1,
                tenant_id INTEGER NOT NULL DEFAULT 0,
                deleted_at TEXT NULL
            )',
            'mysql' => 'CREATE TABLE hook_items (
                hook_item_id INTEGER PRIMARY KEY AUTO_INCREMENT,
                name TEXT NOT NULL,
                version INTEGER NOT NULL DEFAULT 1,
                tenant_id INTEGER NOT NULL DEFAULT 0,
                deleted_at TEXT NULL
            )',
            default => 'CREATE TABLE hook_items (
                hook_item_id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                version INTEGER NOT NULL DEFAULT 1,
                tenant_id INTEGER NOT NULL DEFAULT 0,
                deleted_at TEXT NULL
            )',
        };
    }

    private function managerWithFreshDb(): OrmManager
    {
        $pdo = DbConnection::get();
        $pdo->exec('DROP TABLE IF EXISTS hook_items');
        $pdo->exec(self::hookItemsTableDdl());

        EntityCache::clear();

        $container = new TestContainer();
        $container->set(PDO::class, $pdo);

        return OrmManager::initialize($container);
    }

    public function testVersionColumnHookIncrementsRowInDatabase(): void
    {
        $manager = $this->managerWithFreshDb();
        $pdo = $manager->getPDO();
        $repo = new HookItemRepository($manager, new VersionColumnHooks('version', 'version', true));

        /** @var HookItem $item */
        $item = $repo->createEntity([
            'name' => 'first',
            'version' => 1,
            'tenant_id' => 0,
            'deleted_at' => null,
        ]);
        $repo->save($item);
        $id = $item->getId();
        $this->assertNotNull($id);

        $v = (int) $pdo->query('SELECT version FROM hook_items WHERE hook_item_id = ' . (int) $id)->fetchColumn();
        $this->assertSame(1, $v);
        $this->assertSame(1, $item->getVersion());

        $item->setName('second');
        $repo->save($item);

        $v = (int) $pdo->query('SELECT version FROM hook_items WHERE hook_item_id = ' . (int) $id)->fetchColumn();
        $this->assertSame(2, $v);
        $this->assertSame(2, $item->getVersion());
    }

    public function testVersionColumnThrowsOptimisticLockWhenVersionChangedConcurrently(): void
    {
        $manager = $this->managerWithFreshDb();
        $pdo = $manager->getPDO();
        $repo = new HookItemRepository($manager, new VersionColumnHooks('version', 'version', true));

        /** @var HookItem $item */
        $item = $repo->createEntity([
            'name' => 'first',
            'version' => 1,
            'tenant_id' => 0,
            'deleted_at' => null,
        ]);
        $repo->save($item);
        $id = $item->getId();
        $this->assertNotNull($id);

        $item->setName('second');
        $repo->save($item);
        $this->assertSame(2, $item->getVersion());

        $pdo->exec('UPDATE hook_items SET version = 99 WHERE hook_item_id = ' . (int) $id);

        $item->setName('third');
        $this->expectException(OptimisticLockException::class);
        $repo->save($item);
    }

    public function testTenantScopeAndSoftDeleteHooksFilterQuery(): void
    {
        $manager = $this->managerWithFreshDb();
        $pdo = $manager->getPDO();

        $pdo->exec("INSERT INTO hook_items (name, version, tenant_id, deleted_at) VALUES ('a', 1, 1, NULL)");
        $pdo->exec("INSERT INTO hook_items (name, version, tenant_id, deleted_at) VALUES ('b', 1, 2, NULL)");
        $pdo->exec("INSERT INTO hook_items (name, version, tenant_id, deleted_at) VALUES ('c', 1, 1, '2000-01-01 00:00:00')");

        $hooks = new CompositeHooks([
            new TenantScopeHooks('tenant_id', 1),
            new SoftDeleteHooks('deleted_at'),
        ]);
        $repo = new HookItemRepository($manager, $hooks);

        $rows = $repo->sqlQuery()->orderBy('hook_item_id')->getResult();
        $this->assertCount(1, $rows);
        $this->assertSame('a', $rows[0]->getName());
    }
}
