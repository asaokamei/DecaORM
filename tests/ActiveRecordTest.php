<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\RepositoryManager;
use WScore\DecaORM\Tests\Users\Container;
use WScore\DecaORM\Tests\Users\Post;
use WScore\DecaORM\Tests\Users\PostsRepository;
use WScore\DecaORM\Tests\Users\User;
use WScore\DecaORM\Tests\Users\UserRepository;
use WScore\DecaORM\Trait\ActiveRecordTrait;

use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\AbstractRepository;

class ActiveRecordTest extends TestCase
{
    private PDO $pdo;
    private ARTestUserRepository $userRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = file_get_contents(__DIR__ . '/Users/users.sql');
        $this->pdo->exec($sql);
        $sql = file_get_contents(__DIR__ . '/Users/posts.sql');
        $this->pdo->exec($sql);

        EntityCache::clear();

        $container = new Container();
        $manager = RepositoryManager::initialize($container);
        $userRepo = new ARTestUserRepository($this->pdo, $manager);
        $postsRepo = new ARTestPostsRepository($this->pdo, $manager);
        $container->set(ARTestUserRepository::class, $userRepo);
        $container->set(ARTestPostsRepository::class, $postsRepo);

        $this->userRepo = $userRepo;
    }

    protected function createUser($data): ARTestUser
    {
        $user = new ARTestUser();
        $user->fill($data);
        return $user;
    }

    public function testSaveAndFindById(): void
    {
        $user = $this->createUser([
            'name' => 'AR User',
            'email' => 'ar@example.com'
        ]);
        $user->save();

        $userId = $user->getId();
        $this->assertNotNull($userId);

        EntityCache::clear();

        $found = $this->userRepo->findById($userId);
        $this->assertInstanceOf(ARTestUser::class, $found);
        $this->assertEquals('SETTER: AR User', $found->get('name'));
    }

    public function testCreate(): void
    {
        $user = $this->createUser([
            'name' => 'Created User',
            'email' => 'created@example.com'
        ]);

        $this->assertInstanceOf(ARTestUser::class, $user);
        $this->assertNull($user->getId(), 'create should NOT save the entity');
        $this->assertEquals('SETTER: Created User', $user->get('name'));

        // 明示的に保存
        $user->save();
        $this->assertNotNull($user->getId(), 'save should persist the entity');
    }

    public function testDelete(): void
    {
        $user = $this->createUser([
            'name' => 'To Be Deleted',
            'email' => 'delete@example.com'
        ])->save();
        $userId = $user->getId();

        $user->delete();

        EntityCache::clear();
        $found = $this->userRepo->findById($userId);
        $this->assertNull($found);
    }

    public function testFillUsesSetter(): void
    {
        $user = new ARTestUser();
        $user->fill([
            'name' => 'Setter User',
            'email' => 'setter@example.com'
        ]);

        $this->assertTrue($user->setterCalled, 'Setter method should be called');
        $this->assertEquals('SETTER: Setter User', $user->get('name'));
    }

    public function testFillMassAssignmentProtection(): void
    {
        $user = new ARTestUser();
        $user->fill([
            'id' => 999,
            'name' => 'Protected User',
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]);

        $this->assertNull($user->getId(), 'ID should NOT be filled');
        $this->assertNull($user->get('created_at'), 'CreatedAt should NOT be filled');
        $this->assertNull($user->get('updated_at'), 'UpdatedAt should NOT be filled');
        $this->assertEquals('SETTER: Protected User', $user->get('name'), 'Other fields should be filled');
    }

    public function testFillWithFillable(): void
    {
        $user = new ARTestUserFillable();
        $user->fill([
            'name' => 'Fillable Name',
            'email' => 'hidden@example.com',
        ]);

        $this->assertEquals('Fillable Name', $user->get('name'));
        $this->assertEquals('', $user->get('email'), 'Email should NOT be filled as it is not in fillable');
    }

    public function testFillWithGuarded(): void
    {
        $user = new ARTestUserGuarded();
        $user->fill([
            'name' => 'Guarded Name',
            'email' => 'guarded@example.com',
        ]);

        $this->assertEquals('Guarded Name', $user->get('name'));
        $this->assertEquals('', $user->get('email'), 'Email should NOT be filled as it is in guarded');
    }

    public function testFillWithMethod(): void
    {
        $user = new ARTestUserMethod();
        $user->fill([
            'name' => 'Method Name',
            'email' => 'method@example.com',
        ]);

        $this->assertEquals('Method Name', $user->get('name'));
        $this->assertEquals('', $user->get('email'), 'Email should NOT be filled as per isFillable method');
    }
}

// テスト用クラス
/**
 * @WScore\DecaORM\Attribute\Table(name: "users")
 * @WScore\DecaORM\Attribute\Repository(ARTestUserRepository::class)
 */
#[\WScore\DecaORM\Attribute\Table(name: 'users')]
#[\WScore\DecaORM\Attribute\Repository(ARTestUserRepository::class)]
class ARTestUser implements \WScore\DecaORM\EntityInterface {
    use \WScore\DecaORM\Trait\EntityTrait;
    use ActiveRecordTrait;

    #[\WScore\DecaORM\Attribute\Id]
    #[\WScore\DecaORM\Attribute\GeneratedValue]
    #[\WScore\DecaORM\Attribute\Column(name: 'user_id')]
    private ?string $id = null;

    #[\WScore\DecaORM\Attribute\Column(name: 'user_name')]
    private string $name = '';

    #[\WScore\DecaORM\Attribute\Column(name: 'email')]
    private string $email = '';

    #[\WScore\DecaORM\Attribute\CreatedAt]
    private ?string $created_at = null;

    #[\WScore\DecaORM\Attribute\UpdatedAt]
    private ?string $updated_at = null;

    public bool $setterCalled = false;

    public function setName(string $name): void
    {
        $this->setterCalled = true;
        $this->name = 'SETTER: ' . $name;
    }

    public function getId(): ?int {
        return $this->id !== null ? (int) $this->id : null;
    }
}

#[\WScore\DecaORM\Attribute\Table(name: 'users')]
#[\WScore\DecaORM\Attribute\Repository(ARTestUserRepository::class)]
class ARTestUserFillable implements \WScore\DecaORM\EntityInterface {
    use \WScore\DecaORM\Trait\EntityTrait;
    use ActiveRecordTrait;

    public static array $fillable = ['name'];

    #[\WScore\DecaORM\Attribute\Id]
    #[\WScore\DecaORM\Attribute\GeneratedValue]
    #[\WScore\DecaORM\Attribute\Column(name: 'user_id')]
    private ?string $id = null;

    #[\WScore\DecaORM\Attribute\Column(name: 'user_name')]
    private string $name = '';

    #[\WScore\DecaORM\Attribute\Column(name: 'email')]
    private string $email = '';

    public function getId(): ?int {
        return $this->id !== null ? (int) $this->id : null;
    }
}

#[\WScore\DecaORM\Attribute\Table(name: 'users')]
#[\WScore\DecaORM\Attribute\Repository(ARTestUserRepository::class)]
class ARTestUserGuarded implements \WScore\DecaORM\EntityInterface {
    use \WScore\DecaORM\Trait\EntityTrait;
    use ActiveRecordTrait;

    public static array $guarded = ['email'];

    #[\WScore\DecaORM\Attribute\Id]
    #[\WScore\DecaORM\Attribute\GeneratedValue]
    #[\WScore\DecaORM\Attribute\Column(name: 'user_id')]
    private ?string $id = null;

    #[\WScore\DecaORM\Attribute\Column(name: 'user_name')]
    private string $name = '';

    #[\WScore\DecaORM\Attribute\Column(name: 'email')]
    private string $email = '';

    public function getId(): ?int {
        return $this->id !== null ? (int) $this->id : null;
    }
}

#[\WScore\DecaORM\Attribute\Table(name: 'users')]
#[\WScore\DecaORM\Attribute\Repository(ARTestUserRepository::class)]
class ARTestUserMethod implements \WScore\DecaORM\EntityInterface {
    use \WScore\DecaORM\Trait\EntityTrait;
    use ActiveRecordTrait;

    public function isFillable(string $key): bool {
        return $key !== 'email';
    }

    #[\WScore\DecaORM\Attribute\Id]
    #[\WScore\DecaORM\Attribute\GeneratedValue]
    #[\WScore\DecaORM\Attribute\Column(name: 'user_id')]
    private ?string $id = null;

    #[\WScore\DecaORM\Attribute\Column(name: 'user_name')]
    private string $name = '';

    #[\WScore\DecaORM\Attribute\Column(name: 'email')]
    private string $email = '';

    public function getId(): ?int {
        return $this->id !== null ? (int) $this->id : null;
    }
}

#[\WScore\DecaORM\Attribute\Table(name: 'posts')]
#[\WScore\DecaORM\Attribute\Repository(ARTestPostsRepository::class)]
class ARTestPost implements \WScore\DecaORM\EntityInterface {
    use \WScore\DecaORM\Trait\EntityTrait;
    use ActiveRecordTrait;

    #[\WScore\DecaORM\Attribute\Id]
    #[\WScore\DecaORM\Attribute\GeneratedValue]
    #[\WScore\DecaORM\Attribute\Column(name: 'post_id')]
    private ?string $post_id = null;

    #[\WScore\DecaORM\Attribute\Column(name: 'user_id')]
    private ?string $user_id = null;

    #[\WScore\DecaORM\Attribute\Column(name: 'title')]
    private string $title = '';

    #[\WScore\DecaORM\Attribute\Column(name: 'content')]
    private string $content = '';

    #[\WScore\DecaORM\Attribute\BelongsTo(targetEntity: ARTestUser::class, foreignKey: 'user_id')]
    private ?ARTestUser $user = null;

    public function getId(): ?int {
        return $this->post_id !== null ? (int) $this->post_id : null;
    }
}

class ARTestUserRepository extends AbstractRepository {
    public function __construct(PDO $pdo, $manager = null) {
        $this->db = $pdo;
        $this->hydrator = new AttributeHydrator(ARTestUser::class);
        $this->manager = $manager;
        $this->now = new \DateTimeImmutable();
    }
}

class ARTestPostsRepository extends AbstractRepository {
    public function __construct(PDO $pdo, $manager = null) {
        $this->db = $pdo;
        $this->hydrator = new AttributeHydrator(ARTestPost::class);
        $this->manager = $manager;
        $this->now = new \DateTimeImmutable();
    }
}
