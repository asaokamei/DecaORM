<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\HydratorInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Tests\Fixtures\TestEntity;
use WScore\DecaORM\Tests\Fixtures\Relations\Post;
use WScore\DecaORM\Tests\Fixtures\Relations\PostRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;

class EntityCollectionTest extends TestCase
{
    /** 同一クラス（TestEntity）のエンティティを作成。getRaw は $data を返す。 */
    private function createEntity(int|string $id, array $data = []): TestEntity
    {
        return new TestEntity($id, $data);
    }

    public function testBasicMethods()
    {
        $e1 = $this->createEntity(1);
        $e2 = $this->createEntity(2);

        $collection = new EntityCollection([$e1, $e2], null);

        $this->assertCount(2, $collection);
        $this->assertEquals([1, 2], $collection->getIds());
        $this->assertSame([$e1, $e2], $collection->getEntities());

        // Iterator のテスト
        $iterated = [];
        foreach ($collection as $e) {
            $iterated[] = $e;
        }
        $this->assertCount(2, $iterated);
    }

    public function testMapAndFilter()
    {
        $e1 = $this->createEntity(1, ['name' => 'Alice']);
        $e2 = $this->createEntity(2, ['name' => 'Bob']);

        $collection = new EntityCollection([$e1, $e2], null);

        // map
        $names = $collection->map(fn($e) => $e->getRaw('name'));
        $this->assertEquals(['Alice', 'Bob'], $names);

        // getValues
        $this->assertEquals(['Alice', 'Bob'], $collection->getValues('name'));

        // filter
        $filtered = $collection->filter(fn($e) => $e->getRaw('name') === 'Alice');
        $this->assertInstanceOf(EntityCollection::class, $filtered);
        $this->assertCount(1, $filtered);
        $this->assertEquals([1], $filtered->getIds());
    }

    public function testSortByProperty()
    {
        $e1 = $this->createEntity(1, ['rank' => 20]);
        $e2 = $this->createEntity(2, ['rank' => 10]);
        $e3 = $this->createEntity(3, ['rank' => 30]);

        $collection = new EntityCollection([$e1, $e2, $e3], null);

        // 文字列によるソート
        $collection->sort('rank');
        $this->assertEquals([2, 1, 3], $collection->getIds());

        // 配列による複数条件ソート
        $e4 = $this->createEntity(4, ['rank' => 10, 'sub' => 'a']);
        $e5 = $this->createEntity(5, ['rank' => 10, 'sub' => 'b']);
        $collection = new EntityCollection([$e5, $e4], null);
        $collection->sort(['rank', 'sub']);
        $this->assertEquals([4, 5], $collection->getIds());
    }

    public function testChunk()
    {
        $entities = [
            $this->createEntity(1),
            $this->createEntity(2),
            $this->createEntity(3),
        ];
        $collection = new EntityCollection($entities, null);

        $chunks = $collection->chunk(2);

        $this->assertCount(2, $chunks);
        $this->assertCount(2, $chunks[0]);
        $this->assertCount(1, $chunks[1]);
        $this->assertInstanceOf(EntityCollection::class, $chunks[0]);
    }

    public function testFillAndUniqueness()
    {
        // In-memory SQLite database for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create users table
        $sql = file_get_contents(__DIR__ . '/Fixtures/Relations/Sql/users.sql');
        $pdo->exec($sql);

        // Create posts table
        $sql = file_get_contents(__DIR__ . '/Fixtures/Relations/Sql/posts.sql');
        $pdo->exec($sql);

        // prepare repositories
        $container = new TestContainer();
        $container->set(PDO::class, $pdo);
        $manager = OrmManager::initialize($container);
        $userRepo = new UserRepository($manager);
        $postsRepo = new PostRepository($manager);
        $container->set(UserRepository::class, $userRepo);
        $container->set(PostRepository::class, $postsRepo);

        // Start TESTING!

        $user1 = $userRepo->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $userRepo->save($user1);
        $user2 = $userRepo->create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $userRepo->save($user2);
        $post1 = $postsRepo->create(['user_id' => $user1->getId(), 'title' => 'U1/P1', 'content' => 'Content 1']);
        $postsRepo->save($post1);
        $post1 = $postsRepo->create(['user_id' => $user1->getId(), 'title' => 'U1/P2', 'content' => 'Content 2']);
        $postsRepo->save($post1);
        $post1 = $postsRepo->create(['user_id' => $user2->getId(), 'title' => 'U2/P1', 'content' => 'Content 3']);
        $postsRepo->save($post1);
        $post1 = $postsRepo->create(['user_id' => $user2->getId(), 'title' => 'U2/P2', 'content' => 'Content 4']);
        $postsRepo->save($post1);
        $post1 = $postsRepo->create(['user_id' => $user2->getId(), 'title' => 'U2/P3', 'content' => 'Content 5']);
        $postsRepo->save($post1);

        EntityCache::clear();

        $users = $userRepo->sqlQuery()->getCollection();
        $this->assertEquals(2, $users->count());
        $this->assertEquals([1, 2], $users->getIds());
        $this->assertEquals(['Alice', 'Bob'], $users->getValues('name'));


        $posts = $users->load('posts');
        $this->assertCount(5, $posts);

        $posts1 = $users[0]->getRaw('posts');
        $this->assertCount(2, $posts1);
        $this->assertEquals(1, $posts1[0]->getId());
        $this->assertEquals('U1/P1', $posts1[0]->getRaw('title'));

        $this->assertEquals(2, $posts1[1]->getId());;
        $this->assertEquals('U1/P2', $posts1[1]->getRaw('title'));

        $posts2 = $users[1]->getRaw('posts');
        $this->assertCount(3, $posts2);
        $this->assertEquals(3, $posts2[0]->getId());
        $this->assertEquals('U2/P1', $posts2[0]->getRaw('title'));
        $this->assertEquals(4, $posts2[1]->getId());
        $this->assertEquals('U2/P2', $posts2[1]->getRaw('title'));
        $this->assertEquals(5, $posts2[2]->getId());
        $this->assertEquals('U2/P3', $posts2[2]->getRaw('title'));
    }

    public function testSave()
    {
        $repo = $this->createMock(RepositoryInterface::class);
        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->method('getEntityClass')->willReturn(TestEntity::class);
        $repo->method('getHydrator')->willReturn($hydrator);
        $repo->expects($this->exactly(2))->method('save');

        $e1 = $this->createEntity(1);
        $e2 = $this->createEntity(2);
        $collection = new EntityCollection([$e1, $e2], $repo);
        $collection->save();
    }

    /** 異なるエンティティクラスが混在すると InvalidArgumentException */
    public function testRejectsMixedEntityClasses()
    {
        $user = new User();
        $user->setRaw('id', 1);
        $post = new Post();
        $post->setRaw('post_id', 1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EntityCollection requires all entities to be the same class');

        new EntityCollection([$user, $post], null);
    }

    /** 非エンティティが含まれると InvalidArgumentException */
    public function testRejectsNonEntityInConstructor()
    {
        $e = $this->createEntity(1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EntityCollection accepts only');

        new EntityCollection([$e, 'not an entity']);
    }

    /** add で異なるクラスを追加すると InvalidArgumentException */
    public function testRejectsWrongClassOnAdd()
    {
        $collection = new EntityCollection([$this->createEntity(1)], null);

        $user = new User();
        $user->setRaw('id', 2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('EntityCollection requires all entities to be the same class');

        $collection->add($user);
    }

    /** getEntityClass は先頭エンティティのクラスを返す */
    public function testGetEntityClassReturnsFirstEntityClass()
    {
        $e1 = $this->createEntity(1);
        $e2 = $this->createEntity(2);
        $collection = new EntityCollection([$e1, $e2], null);

        $this->assertSame(TestEntity::class, $collection->getEntityClass());
    }

    /** 空コレクションでは getEntityClass は null */
    public function testGetEntityClassNullWhenEmpty()
    {
        $collection = new EntityCollection([], null);
        $this->assertNull($collection->getEntityClass());
    }

    /** シリアライズ時は repository を含めず、復元後はエンティティと entityClass のみ復元される */
    public function testSerializeOmitsRepository()
    {
        $repo = $this->createMock(RepositoryInterface::class);
        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->method('getEntityClass')->willReturn(TestEntity::class);
        $repo->method('getHydrator')->willReturn($hydrator);

        $e1 = $this->createEntity(1, ['name' => 'A']);
        $e2 = $this->createEntity(2, ['name' => 'B']);
        $original = new EntityCollection([$e1, $e2], $repo);

        $restored = unserialize(serialize($original));

        $this->assertInstanceOf(EntityCollection::class, $restored);
        $this->assertCount(2, $restored);
        $this->assertSame([1, 2], $restored->getIds());
        $this->assertSame(TestEntity::class, $restored->getEntityClass());
        $this->assertEquals(['A', 'B'], $restored->getValues('name'));
        // 復元直後は repository は null。load() で OrmManager から解決しようとするが、
        // 未初期化または TestRepository がコンテナに無いため例外になる
        $this->expectException(\Throwable::class);
        $restored->load('posts');
    }

    /** 復元後に OrmManager がセットアップされていれば、load() でリポジトリを解決できる */
    public function testUnserializedCollectionResolvesRepositoryFromOrmManager()
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(file_get_contents(__DIR__ . '/Fixtures/Relations/Sql/users.sql'));
        $pdo->exec(file_get_contents(__DIR__ . '/Fixtures/Relations/Sql/posts.sql'));

        $container = new TestContainer();
        $container->set(PDO::class, $pdo);
        $manager = OrmManager::initialize($container);
        $userRepo = new UserRepository($manager);
        $postsRepo = new PostRepository($manager);
        $container->set(UserRepository::class, $userRepo);
        $container->set(PostRepository::class, $postsRepo);

        $user1 = $userRepo->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $userRepo->save($user1);
        $users = $userRepo->sqlQuery()->getCollection();

        $restored = unserialize(serialize($users));
        $this->assertCount(1, $restored);
        // DI 済みなので load() で OrmManager からリポジトリが解決される
        $posts = $restored->load('posts');
        $this->assertInstanceOf(EntityCollection::class, $posts);
    }
}
