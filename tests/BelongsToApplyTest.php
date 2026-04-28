<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\Entity;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;
use WScore\DecaORM\Tests\Support\DbConnection;
use WScore\DecaORM\Tests\Support\SchemaLoader;
use WScore\DecaORM\Trait\EntityTrait;

#[Table('bt_parents')]
#[Entity]
#[Repository(BtParentRepository::class)]
class BtParent implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'id')]
    public ?int $id = null;

    #[Column(name: 'data_id')]
    public string $data_id = '';

    #[Column(name: 'status')]
    public string $status = '';

    public function getId(): ?int
    {
        return $this->id;
    }
}

#[Table('bt_children')]
#[Entity]
#[Repository(BtChildRepository::class)]
class BtChild implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'id')]
    public ?int $id = null;

    #[Column(name: 'data_id')]
    public string $data_id = '';

    #[BelongsTo(targetEntity: BtParent::class, foreignKey: 'data_id', ownerKey: 'data_id', apply: 'onlyActive')]
    public ?BtParent $parent = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}

#[Repository(BtParent::class)]
class BtParentRepository extends \WScore\DecaORM\AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, BtParent::class);
    }
}

#[Repository(BtChild::class)]
class BtChildRepository extends \WScore\DecaORM\AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, BtChild::class);
    }

    public function onlyActive(Query $query, EntityInterface|EntityCollection $children): void
    {
        $query->where('status', 'ACTIVE');
    }
}

final class BelongsToApplyTest extends TestCase
{
    private PDO $pdo;
    private OrmManager $manager;
    private BtParentRepository $parentRepo;
    private BtChildRepository $childRepo;

    protected function setUp(): void
    {
        $this->pdo = DbConnection::get();
        $this->pdo->exec(SchemaLoader::loadTable('drop_all'));
        $this->pdo->exec(SchemaLoader::loadTable('bt_parents'));
        $this->pdo->exec(SchemaLoader::loadTable('bt_children'));

        $container = new TestContainer();
        $container->set(PDO::class, $this->pdo);
        $this->manager = OrmManager::initialize($container);
        $this->parentRepo = new BtParentRepository($this->manager);
        $this->childRepo = new BtChildRepository($this->manager);
        $container->set(BtParentRepository::class, $this->parentRepo);
        $container->set(BtChildRepository::class, $this->childRepo);
    }

    public function testBelongsToOwnerKeyAndApplyFiltersActiveSingle(): void
    {
        $active = $this->parentRepo->createEntity(['data_id' => 'D1', 'status' => 'ACTIVE']);
        $this->parentRepo->save($active);
        $deleted = $this->parentRepo->createEntity(['data_id' => 'D1', 'status' => 'DELETED']);
        $this->parentRepo->save($deleted);

        $child = $this->childRepo->createEntity(['data_id' => 'D1']);
        $this->childRepo->save($child);

        $this->manager->getEntityCache()->clear();
        $child = $this->childRepo->findById($child->getId());

        $loaded = $this->childRepo->load($child, 'parent');

        $this->assertCount(1, $loaded);
        $this->assertInstanceOf(BtParent::class, $loaded[0]);
        $this->assertEquals('ACTIVE', $loaded[0]->getRaw('status'));

        $prop = $child->getRaw('parent');
        $this->assertInstanceOf(BtParent::class, $prop);
        $this->assertEquals('ACTIVE', $prop->getRaw('status'));
    }

    public function testBelongsToOwnerKeyAndApplyFiltersActiveBatch(): void
    {
        $a1 = $this->parentRepo->createEntity(['data_id' => 'D1', 'status' => 'ACTIVE']);
        $this->parentRepo->save($a1);
        $d1 = $this->parentRepo->createEntity(['data_id' => 'D1', 'status' => 'DELETED']);
        $this->parentRepo->save($d1);

        $a2 = $this->parentRepo->createEntity(['data_id' => 'D2', 'status' => 'ACTIVE']);
        $this->parentRepo->save($a2);

        $c1 = $this->childRepo->createEntity(['data_id' => 'D1']);
        $this->childRepo->save($c1);
        $c2 = $this->childRepo->createEntity(['data_id' => 'D2']);
        $this->childRepo->save($c2);

        $this->manager->getEntityCache()->clear();
        $children = [
            $this->childRepo->findById($c1->getId()),
            $this->childRepo->findById($c2->getId()),
        ];

        $loaded = $this->childRepo->load($children, 'parent');
        $this->assertCount(2, $loaded);

        $p1 = $children[0]->getRaw('parent');
        $p2 = $children[1]->getRaw('parent');

        $this->assertEquals('D1', $p1->getRaw('data_id'));
        $this->assertEquals('ACTIVE', $p1->getRaw('status'));
        $this->assertEquals('D2', $p2->getRaw('data_id'));
        $this->assertEquals('ACTIVE', $p2->getRaw('status'));
    }
}

