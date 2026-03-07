<?php
namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\RepositoryManager;
use WScore\DecaORM\Tests\Users\Container;
use WScore\DecaORM\Tests\Users\Post;
use WScore\DecaORM\Tests\Users\PostsRepository;
use WScore\DecaORM\Tests\Users\Profile;
use WScore\DecaORM\Tests\Users\ProfileRepository;
use WScore\DecaORM\Tests\Users\User;
use WScore\DecaORM\Tests\Users\UserRepository;

class RelationsAutoForeignKeyTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private PostsRepository $postsRepo;
    private ProfileRepository $profileRepo;

    protected function setUp(): void
    {
        // In-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create users table
        $this->pdo->exec(
            "CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )"
        );

        // Create posts table
        $this->pdo->exec(
            "CREATE TABLE posts (
            post_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )"
        );

        // Create profiles table (1:1 with users)
        $this->pdo->exec(
            "CREATE TABLE profiles (
            profile_id INTEGER PRIMARY KEY,
            nickname TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT,
            FOREIGN KEY (profile_id) REFERENCES users(user_id)
        )"
        );

        // Clear cache before each test
        EntityCache::clear();

        $container = new Container();
        $manager = RepositoryManager::initialize($container);
        $this->userRepo = new UserRepository($this->pdo, $manager);
        $this->postsRepo = new PostsRepository($this->pdo, $manager);
        $this->profileRepo = new ProfileRepository($this->pdo, $manager);
        $container->set(UserRepository::class, $this->userRepo);
        $container->set(PostsRepository::class, $this->postsRepo);
        $container->set(ProfileRepository::class, $this->profileRepo);
    }

    public function testHasMany_childrenForeignKeysFilledOnParentInsert(): void
    {
        // Prepare a parent with in-memory children (no user_id set yet)
        /** @var User $user */
        $user = $this->userRepo->createEntity([
            'name' => 'Parent User',
            'email' => 'parent@example.com',
        ]);

        $post1 = new Post();
        $post1->set('title', 'p1');
        $post1->set('content', 'c1');

        $post2 = new Post();
        $post2->set('title', 'p2');
        $post2->set('content', 'c2');

        // Assign children via HasMany property
        $user->set('posts', [$post1, $post2]);

        // Save parent (insert). This should trigger filling children's back-reference and FK
        $this->userRepo->save($user);

        $this->assertNotNull($user->getId(), 'Parent should have an ID after insert');

        // Verify children got user FK and back-reference set
        foreach ([$post1, $post2] as $i => $post) {
            $msg = "post#" . ($i + 1);
            // user_id may be stored as string; compare with == to ignore type
            $this->assertEquals($user->getId(), $post->get('user_id'), $msg . ' should get user_id filled');
            $this->assertSame($user, $post->get('user'), $msg . ' should get user back-reference filled');
        }
    }

    public function testHasOne_childForeignKeyFilledOnParentInsert(): void
    {
        // Prepare a parent with in-memory child (no profile_id set yet)
        /** @var User $user */
        $user = $this->userRepo->createEntity([
            'name' => 'User With Profile',
            'email' => 'with.profile@example.com',
        ]);

        $profile = new Profile();
        $profile->setNickname('nick');

        // Assign child via HasOne property
        $user->set('profile', $profile);

        // Save parent (insert). This should trigger filling child's back-reference and FK (profile.id)
        $this->userRepo->save($user);

        $this->assertNotNull($user->getId(), 'Parent should have an ID after insert');

        // Profile has private fields; verify via accessor
        $this->assertSame($user->getId(), $profile->getId(), 'Profile primary key should match user id');
        $this->assertSame($user, $profile->getUser(), 'Profile back-reference should be set to user');
    }
}
