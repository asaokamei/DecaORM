<?php

namespace WScore\DecaORM\Tests\Persistence;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\HydratorInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\Contracts\OptimisticLockException;
use WScore\DecaORM\Persistence\SoftDeleteHooks;
use WScore\DecaORM\Persistence\TenantScopeHooks;
use WScore\DecaORM\Persistence\VersionColumnHooks;
use WScore\DecaORM\Sql\Delete;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Sql\Update;

class SamplePersistenceHooksTest extends TestCase
{
    private function repositoryForQuery(): RepositoryInterface
    {
        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->method('getTableName')->willReturn('items');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');

        $repo = $this->createMock(RepositoryInterface::class);
        $repo->method('getHydrator')->willReturn($hydrator);
        $repo->method('getDb')->willReturn($pdo);
        $repo->method('getTableName')->willReturn('items');

        return $repo;
    }

    private function repositoryForUpdate(): RepositoryInterface
    {
        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->method('getTableName')->willReturn('items');
        $hydrator->method('getPrimaryKeyColumn')->willReturn('id');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');

        $repo = $this->createMock(RepositoryInterface::class);
        $repo->method('getHydrator')->willReturn($hydrator);
        $repo->method('getDb')->willReturn($pdo);
        $repo->method('getTableName')->willReturn('items');
        $repo->method('execute')->willReturn(true);

        return $repo;
    }

    public function testTenantScopeAddsWhere(): void
    {
        $repo = $this->repositoryForQuery();
        $query = new Query($repo);
        $hook = new TenantScopeHooks('tenant_id', 7);
        $hook->beforeQuery($query);

        $sql = $query->getSql();
        $this->assertStringContainsString('tenant_id', $sql);
        $params = $query->getParameters();
        $this->assertContains(7, $params);
    }

    public function testSoftDeleteAddsNullCheck(): void
    {
        $repo = $this->repositoryForQuery();
        $query = new Query($repo);
        $hook = new SoftDeleteHooks('deleted_at');
        $hook->beforeQuery($query);

        $this->assertStringContainsString('deleted_at IS NULL', $query->getSql());
    }

    public function testSoftDeleteCanRejectPhysicalDelete(): void
    {
        $hook = new SoftDeleteHooks('deleted_at', true);
        $repo = $this->repositoryForUpdate();
        $delete = new Delete($repo);
        $delete->setId(1);
        $entity = $this->createMock(EntityInterface::class);

        $this->expectException(\RuntimeException::class);
        $hook->beforeDelete($delete, $entity);
    }

    public function testVersionColumnAddsPredicateAndIncrement(): void
    {
        $repo = $this->repositoryForUpdate();
        $update = new Update($repo);
        $update->setId(10)->data(['name' => 'x']);

        $entity = $this->createMock(EntityInterface::class);
        $entity->expects($this->once())->method('setRaw')->with('version', 5);

        $hook = new VersionColumnHooks('version', 'version', true);
        $hook->beforeUpdate($update, $entity, ['name' => 'x'], ['version' => 4, 'name' => 'old']);

        $sql = $update->getSql();
        $this->assertStringContainsString('version', $sql);
        $this->assertStringContainsString('version=version+1', str_replace(' ', '', $sql));

        $hook->afterUpdate($entity);
    }

    public function testVersionColumnThrowsWhenVersionInDiff(): void
    {
        $repo = $this->repositoryForUpdate();
        $update = new Update($repo);
        $update->setId(1)->data(['version' => 5]);

        $hook = new VersionColumnHooks('version', null, true);
        $entity = $this->createMock(EntityInterface::class);

        $this->expectException(\RuntimeException::class);
        $hook->beforeUpdate($update, $entity, ['version' => 5], ['version' => 4]);
    }

    public function testVersionColumnThrowsWhenSnapshotStrict(): void
    {
        $repo = $this->repositoryForUpdate();
        $update = new Update($repo);
        $update->setId(1)->data(['name' => 'x']);

        $hook = new VersionColumnHooks('version', null, true);
        $entity = $this->createMock(EntityInterface::class);

        $this->expectException(OptimisticLockException::class);
        $hook->beforeUpdate($update, $entity, ['name' => 'x'], null);
    }
}
