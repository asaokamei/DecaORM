<?php

namespace WScore\DecaORM\Tests;

use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryHooksInterface;
use WScore\DecaORM\Persistence\CompositeHooks;
use WScore\DecaORM\Persistence\NoOpHooks;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Sql\Update;
use WScore\DecaORM\Tests\Fixtures\Relations\RelationsFixture;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;

class PersistenceHooksTest extends TestCase
{
    public function testBeforeQueryFiltersFind(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'Hook', 'email' => 'hook@example.test']);
        $fixture->users->save($user);
        $id = $user->getId();
        $this->assertNotNull($id);

        $repo = new class($fixture->manager) extends UserRepository {
            public function __construct(\WScore\DecaORM\OrmManager $manager)
            {
                parent::__construct($manager);
                $this->hooks = new class extends NoOpHooks {
                    public function beforeQuery(Query $query): void
                    {
                        $query->where('user_id', 999999);
                    }
                };
            }
        };

        $found = $repo->find($id);
        $this->assertCount(0, $found);
    }

    public function testSqlQueryAndNewQueryEachApplyHooks(): void
    {
        $fixture = RelationsFixture::create();
        $recording = new RecordingQueryHooks();

        $repo = new class($fixture->manager, $recording) extends UserRepository {
            public function __construct(\WScore\DecaORM\OrmManager $manager, private RecordingQueryHooks $recording)
            {
                parent::__construct($manager);
                $this->hooks = $this->recording;
            }
        };

        $repo->sqlQuery()->newQuery();
        $this->assertSame(2, $recording->beforeQueryCount);
    }

    public function testCompositeHooksRunInOrder(): void
    {
        $order = [];
        $first = new OrderedQueryHook($order, 'first');
        $second = new OrderedQueryHook($order, 'second');
        $composite = new CompositeHooks([$first, $second]);

        $fixture = RelationsFixture::create();
        $repo = new class($fixture->manager, $composite) extends UserRepository {
            public function __construct(\WScore\DecaORM\OrmManager $manager, RepositoryHooksInterface $hooks)
            {
                parent::__construct($manager);
                $this->hooks = $hooks;
            }
        };

        $repo->sqlQuery();
        $this->assertSame(['first', 'second'], $order);
    }

    public function testBeforeUpdateRunsWhenEntityChanges(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'Before', 'email' => 'before@test']);
        $fixture->users->save($user);

        $recording = new RecordingUpdateHooks();
        $repo = new class($fixture->manager, $recording) extends UserRepository {
            public function __construct(\WScore\DecaORM\OrmManager $manager, private RecordingUpdateHooks $recording)
            {
                parent::__construct($manager);
                $this->hooks = $this->recording;
            }
        };

        $user->setName('After');
        $repo->save($user);
        $this->assertSame(1, $recording->beforeUpdateCount);
    }
}

final class RecordingQueryHooks extends NoOpHooks
{
    public int $beforeQueryCount = 0;

    public function beforeQuery(Query $query): void
    {
        $this->beforeQueryCount++;
    }
}

final class OrderedQueryHook extends NoOpHooks
{
    /** @var list<string> */
    private array $order;

    public function __construct(array &$order, private string $label)
    {
        $this->order = &$order;
    }

    public function beforeQuery(Query $query): void
    {
        $this->order[] = $this->label;
    }
}

final class RecordingUpdateHooks extends NoOpHooks
{
    public int $beforeUpdateCount = 0;

    public function beforeUpdate(Update $update, EntityInterface $entity, array $data, ?array $snapshot): void
    {
        $this->beforeUpdateCount++;
    }
}
