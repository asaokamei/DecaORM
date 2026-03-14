<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Tests\Fixtures\ArrayLogger;
use WScore\DecaORM\Tests\Fixtures\Relations\Comment;
use WScore\DecaORM\Tests\Fixtures\Relations\CommentRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\Post;
use WScore\DecaORM\Tests\Fixtures\Relations\PostRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\Profile;
use WScore\DecaORM\Tests\Fixtures\Relations\ProfileRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\RelationsFixture;
use WScore\DecaORM\Tests\Fixtures\Relations\Role;
use WScore\DecaORM\Tests\Fixtures\Relations\RoleRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;

// --- Mock Classes for Testing ---

require_once __DIR__ . '/../vendor/autoload.php';


// --- Test Case ---

class DecaOrmTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repo;

    protected function setUp(): void
    {
        // In-memory SQLite database for testing
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

        // Clear cache before each test
        \WScore\DecaORM\EntityCache::clear();

        $container = new TestContainer();
        $container->set(PDO::class, $this->pdo);
        $manager = OrmManager::initialize($container);
        $this->repo = new UserRepository($manager);
        $container->set(UserRepository::class, $this->repo);
    }

    public function testCreateAndSaveUser()
    {
        $savedUser = $this->repo->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        $this->repo->save($savedUser);

        $this->assertNotNull($savedUser->getId());
        $this->assertEquals('John Doe', $savedUser->getName());
        $this->assertNotNull($savedUser->getRegisteredAt());
        $this->assertNotNull($savedUser->getUpdatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $savedUser->getRegisteredAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $savedUser->getUpdatedAt());
    }

    public function testFindUser()
    {
        // Setup data
        $this->pdo->exec("INSERT INTO users (user_name, email) VALUES ('Jane Doe', 'jane@example.com')");
        $id = $this->pdo->lastInsertId();

        $user = $this->repo->findById($id);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($id, $user->getId());
        $this->assertEquals('Jane Doe', $user->getName());
        $this->assertEquals('jane@example.com', $user->getEmail());

        EntityCache::clear();
        $stmt = $this->repo->execute('SELECT * FROM users WHERE user_id = ?', [$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Jane Doe', $row['user_name']);

        EntityCache::clear();
        $entities = $this->repo->fetch('SELECT * FROM users WHERE user_id = ?', [$id]);
        $this->assertCount(1, $entities);
        /** @var User $entity */
        $entity = $entities[0];
        $this->assertEquals('Jane Doe', $entity->getName());
        $this->assertEquals($id, $entity->getId());
    }

    public function testUpdateUser()
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail('test@example.com');
        $this->assertNull($user->getId());
        $this->repo->save($user);
        $this->assertNotNull($user->getId());
        $id = $user->getId();

        // Update
        $user->setName('New Name');
        $this->repo->save($user);

        // Reload from DB to verify persistence
        // Clear cache logic relies on HydratorTrait static property,
        // assuming a new request or clearing cache in a real app.
        // For this test, we simulate fetching fresh data or rely on repository.

        // Directly check DB to ensure an update happened.
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('New Name', $row['user_name']);
        $this->assertNotNull($row['updated_at']);
    }

    public function testDeleteUser()
    {
        $user = $this->repo->create([
            'name' => 'To Delete',
            'email' => 'delete@example.com',
        ]);
        $this->repo->save($user);
        $id = $user->getId();

        $this->assertNotNull($user->getId());

        $this->repo->delete($user);

        $this->assertNull($this->repo->findById($id));
    }

    public function testIdentityMapCache()
    {
        $this->pdo->exec("INSERT INTO users (user_name, email) VALUES ('Cache Test', 'cache@example.com')");
        $id = $this->pdo->lastInsertId();

        $user1 = $this->repo->findById($id);
        $user2 = $this->repo->findById($id);

        // HydratorTrait uses a static cache, so the same instance should be returned
        $this->assertSame($user1, $user2);
    }

    public function testExecuteLogsSqlThroughRepositoryManager(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )"
        );

        $container = new TestContainer();
        $container->set(PDO::class, $pdo);
        $logger = new ArrayLogger();
        $manager = OrmManager::initialize($container)
            ->setLogger($logger)
            ->setSlowQueryThresholdMs(0);
        $repo = new UserRepository($manager);

        $repo->execute(
            'INSERT INTO users (user_name, email) VALUES (:name, :email)',
            ['name' => 'Logger Test', 'email' => 'logger@example.com']
        );

        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertSame('SQL executed.', $logger->records[0]['message']);
        $this->assertSame(
            'INSERT INTO users (user_name, email) VALUES (:name, :email)',
            $logger->records[0]['context']['sql']
        );
        $this->assertSame('Logger Test', $logger->records[0]['context']['params']['name']);
        $this->assertArrayHasKey('duration_ms', $logger->records[0]['context']);
    }

    public function testExecuteWorksWithoutConfiguredLogger(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )"
        );

        $container = new TestContainer();
        $container->set(PDO::class, $pdo);
        $manager = OrmManager::initialize($container)
            ->setLogger(null);
        $repo = new UserRepository($manager);

        $stmt = $repo->execute(
            'INSERT INTO users (user_name, email) VALUES (:name, :email)',
            ['name' => 'No Logger', 'email' => 'nologger@example.com']
        );

        $this->assertSame(1, $stmt->rowCount());
        $this->assertSame('1', $pdo->lastInsertId());
    }

    // --- Relation tests (associate, associateBelongsTo, associateHasOne, associateHasMany, associateManyToMany, addHasMany, removeHasMany) ---

    public function testSyncBelongsToPostUser(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'Author', 'email' => 'author@example.com']);
        $fixture->users->save($user);
        $post = $fixture->posts->create($user, ['title' => 'First', 'content' => 'Body']);
        $post->setUser($user);

        $this->assertSame($user, $post->getUser());
        $this->assertEquals($user->getId(), $post->getRaw('user_id'));

        $post->setUser(null);
        $this->assertNull($post->getUser());
        $this->assertNull($post->getRaw('user_id'));

        $post->setUser($user);
        $this->assertSame($user, $post->getUser());
    }

    public function testSyncBelongsToCommentPost(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'U', 'email' => 'u@x.com']);
        $fixture->users->save($user);
        $post = $fixture->posts->create($user, ['title' => 'P', 'content' => 'C']);
        $fixture->posts->save($post);
        $comment = $fixture->comments->create(['body' => 'comment body']);

        $comment->setPost($post);
        $this->assertSame($post, $comment->getPost());
        $this->assertEquals($post->getId(), $comment->getRaw('post_id'));

        $comment->setPost(null);
        $this->assertNull($comment->getPost());
        $this->assertNull($comment->getRaw('post_id'));
    }

    public function testSyncBelongsToUpdatesInverseHasManyWhenLoaded(): void
    {
        $fixture = RelationsFixture::create();
        $user1 = $fixture->users->create(['name' => 'U1', 'email' => 'u1@x.com']);
        $fixture->users->save($user1);
        $user2 = $fixture->users->create(['name' => 'U2', 'email' => 'u2@x.com']);
        $fixture->users->save($user2);

        $post = $fixture->posts->create(['title' => 'P', 'content' => 'C']);
        $user1->setPosts(new EntityCollection([$post], $fixture->posts));
        $user2->setPosts(new EntityCollection([], $fixture->posts));
        $post->setUser($user1);
        $this->assertEquals($user1->getId(), $post->getRaw('user_id'));

        $post->setUser($user2);
        $posts1 = $user1->getRaw('posts');
        $posts2 = $user2->getRaw('posts');
        $this->assertInstanceOf(EntityCollection::class, $posts1);
        $this->assertInstanceOf(EntityCollection::class, $posts2);
        $this->assertFalse($posts1->hasEntity($post));
        $this->assertTrue($posts2->hasEntity($post));
    }

    public function testSyncHasOne(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'U', 'email' => 'u@x.com']);
        $fixture->users->save($user);
        $profile = $fixture->profiles->create(['id' => $user->getId(), 'nickname' => 'Nick']);

        $user->setProfile($profile);
        $this->assertSame($profile, $user->getProfile());
        $this->assertSame($user, $profile->getUser());
        $this->assertEquals($user->getId(), $profile->getId());
    }

    public function testSyncHasMany(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'U', 'email' => 'u@x.com']);
        $fixture->users->save($user);
        $post = $fixture->posts->create($user, ['title' => 'P', 'content' => 'C']);
        $fixture->posts->save($post);

        $c1 = $fixture->comments->create(['body' => 'c1']);
        $c2 = $fixture->comments->create(['body' => 'c2']);
        $post->setComments(new EntityCollection([$c1, $c2], $fixture->comments));

        $this->assertCount(2, $post->getComments());
        $this->assertSame($post, $c1->getPost());
        $this->assertSame($post, $c2->getPost());
        $this->assertEquals($post->getId(), $c1->getRaw('post_id'));
        $this->assertEquals($post->getId(), $c2->getRaw('post_id'));

        $post->setComments(new EntityCollection([$c1], $fixture->comments));
        $this->assertCount(1, $post->getComments());
        $this->assertNull($c2->getPost());
        $this->assertNull($c2->getRaw('post_id'));
    }

    public function testSyncHasManyUserPosts(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'U', 'email' => 'u@x.com']);
        $fixture->users->save($user);
        $p1 = $fixture->posts->create(['title' => 'P1', 'content' => 'C1']);
        $p2 = $fixture->posts->create(['title' => 'P2', 'content' => 'C2']);

        $user->setPosts(new EntityCollection([$p1, $p2], $fixture->posts));
        $this->assertCount(2, $user->getPosts());
        $this->assertSame($user, $p1->getUser());
        $this->assertSame($user, $p2->getUser());

        $user->setPosts(new EntityCollection([$p1], $fixture->posts));
        $this->assertCount(1, $user->getPosts());
        $this->assertNull($p2->getUser());
    }

    public function testAddHasManyRemoveHasManyUserPosts(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'U', 'email' => 'u@x.com']);
        $fixture->users->save($user);
        $p1 = $fixture->posts->create(['title' => 'P1', 'content' => 'C1']);
        $p2 = $fixture->posts->create(['title' => 'P2', 'content' => 'C2']);

        $user->addPost($p1);
        $user->addPost($p2);
        $this->assertCount(2, $user->getPosts());
        $this->assertSame($user, $p1->getUser());
        $this->assertSame($user, $p2->getUser());

        $user->removePost($p1);
        $this->assertCount(1, $user->getPosts());
        $this->assertNull($p1->getUser());
        $this->assertSame($user, $p2->getUser());
    }

    public function testAddHasManyRemoveHasManyPostComments(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'U', 'email' => 'u@x.com']);
        $fixture->users->save($user);
        $post = $fixture->posts->create($user, ['title' => 'P', 'content' => 'C']);
        $fixture->posts->save($post);

        $c1 = $fixture->comments->create(['body' => 'c1']);
        $c2 = $fixture->comments->create(['body' => 'c2']);
        $post->addComment($c1);
        $post->addComment($c2);
        $this->assertCount(2, $post->getComments());
        $this->assertSame($post, $c1->getPost());
        $this->assertSame($post, $c2->getPost());

        $post->removeComment($c1);
        $this->assertCount(1, $post->getComments());
        $this->assertNull($c1->getPost());
        $this->assertSame($post, $c2->getPost());
    }

    public function testSyncManyToManyUserRoles(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'U', 'email' => 'u@x.com']);
        $fixture->users->save($user);
        $r1 = $fixture->roles->create(['name' => 'admin']);
        $fixture->roles->save($r1);
        $r2 = $fixture->roles->create(['name' => 'editor']);
        $fixture->roles->save($r2);

        $user->setRoles(new EntityCollection([$r1, $r2], $fixture->roles));
        $roles = $user->getRoles();
        $this->assertCount(2, $roles);
        $this->assertTrue($roles->hasEntity($r1));
        $this->assertTrue($roles->hasEntity($r2));

        $user->setRoles(new EntityCollection([$r1], $fixture->roles));
        $this->assertCount(1, $user->getRoles());
    }

    public function testSyncManyToManyRoleUsers(): void
    {
        $fixture = RelationsFixture::create();
        $role = $fixture->roles->create(['name' => 'viewer']);
        $fixture->roles->save($role);
        $u1 = $fixture->users->create(['name' => 'U1', 'email' => 'u1@x.com']);
        $fixture->users->save($u1);
        $u2 = $fixture->users->create(['name' => 'U2', 'email' => 'u2@x.com']);
        $fixture->users->save($u2);

        $role->setUsers(new EntityCollection([$u1, $u2], $fixture->users));
        $users = $role->getUsers();
        $this->assertCount(2, $users);
        $this->assertTrue($users->hasEntity($u1));
        $this->assertTrue($users->hasEntity($u2));
    }

    public function testAddHasManyIdempotent(): void
    {
        $fixture = RelationsFixture::create();
        $user = $fixture->users->create(['name' => 'U', 'email' => 'u@x.com']);
        $fixture->users->save($user);
        $post = $fixture->posts->create(['title' => 'P', 'content' => 'C']);

        $user->addPost($post);
        $user->addPost($post);
        $this->assertCount(1, $user->getPosts());
    }
}