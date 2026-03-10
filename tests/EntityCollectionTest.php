<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Tests\Fixtures\Relations\PostRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;

class EntityCollectionTest extends TestCase
{
    /**
     * エンティティのモックを作成するヘルパー
     */
    private function createEntityMock($id, $data = [])
    {
        $entity = $this->createMock(EntityInterface::class);
        $entity->method('getId')->willReturn($id);
        $entity->method('getRaw')->willReturnCallback(fn($key) => $data[$key] ?? null);
        return $entity;
    }

    public function testBasicMethods()
    {
        $repo = $this->createMock(RepositoryInterface::class);
        $e1 = $this->createEntityMock(1);
        $e2 = $this->createEntityMock(2);

        $collection = new EntityCollection([$e1, $e2], $repo);

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
        $repo = $this->createMock(RepositoryInterface::class);
        $e1 = $this->createEntityMock(1, ['name' => 'Alice']);
        $e2 = $this->createEntityMock(2, ['name' => 'Bob']);

        $collection = new EntityCollection([$e1, $e2], $repo);

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
        $repo = $this->createMock(RepositoryInterface::class);
        $e1 = $this->createEntityMock(1, ['rank' => 20]);
        $e2 = $this->createEntityMock(2, ['rank' => 10]);
        $e3 = $this->createEntityMock(3, ['rank' => 30]);

        $collection = new EntityCollection([$e1, $e2, $e3], $repo);;

        // 文字列によるソート
        $collection->sort('rank');
        $this->assertEquals([2, 1, 3], $collection->getIds());

        // 配列による複数条件ソート
        $e4 = $this->createEntityMock(4, ['rank' => 10, 'sub' => 'a']);
        $e5 = $this->createEntityMock(5, ['rank' => 10, 'sub' => 'b']);
        $collection = new EntityCollection([$e5, $e4], $repo);
        $collection->sort(['rank', 'sub']);
        $this->assertEquals([4, 5], $collection->getIds());
    }

    public function testChunk()
    {
        $repo = $this->createMock(RepositoryInterface::class);
        $entities = [
            $this->createEntityMock(1),
            $this->createEntityMock(2),
            $this->createEntityMock(3),
        ];
        $collection = new EntityCollection($entities, $repo);

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
        $e1 = $this->createEntityMock(1);
        $e2 = $this->createEntityMock(2);

        $repo->expects($this->exactly(2))->method('save');

        $collection = new EntityCollection([$e1, $e2], $repo);;
        $collection->save();
    }
}
