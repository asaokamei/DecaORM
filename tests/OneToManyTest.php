<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\Tests\Users\Post;
use WScore\DecaORM\Tests\Users\PostsRepository;
use WScore\DecaORM\Tests\Users\User;
use WScore\DecaORM\Tests\Users\UserRepository;

class OneToManyTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private PostsRepository $postsRepo;

    protected function setUp(): void
    {
        // In-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create users table
        $this->pdo->exec(
            "CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
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

        // Clear cache before each test
        \WScore\DecaORM\EntityCache::clear();

        $this->userRepo = new UserRepository($this->pdo, new AttributeHydrator(User::class));
        $this->postsRepo = new PostsRepository($this->pdo, new AttributeHydrator(Post::class));
    }

    public function testCreateUserAndPosts(): void
    {
        // Create a user
        $user = $this->userRepo->createAndSave([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $this->assertNotNull($user->getId());
        $this->assertEquals('John Doe', $user->get('name'));

        // Create posts for the user
        $post1 = $this->postsRepo->createAndSave([
            'user_id' => $user->getId(),
            'title' => 'First Post',
            'content' => 'This is the first post'
        ]);

        $post2 = $this->postsRepo->createAndSave([
            'user_id' => $user->getId(),
            'title' => 'Second Post',
            'content' => 'This is the second post'
        ]);

        $this->assertNotNull($post1->getId());
        $this->assertNotNull($post2->getId());
        $this->assertEquals('First Post', $post1->get('title'));
        $this->assertEquals('Second Post', $post2->get('title'));
    }

    public function testLoadUserForPost(): void
    {
        // Create a user
        $user = $this->userRepo->createAndSave([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);

        // Create a post
        $post = $this->postsRepo->createAndSave([
            'user_id' => $user->getId(),
            'title' => 'Test Post',
            'content' => 'Test content'
        ]);

        // Load user for the post (ManyToOne)
        $this->userRepo->loadUserForPost($post);

        // Verify the user is loaded
        $loadedUser = $post->get('user');
        $this->assertInstanceOf(User::class, $loadedUser);
        $this->assertEquals($user->getId(), $loadedUser->getId());
        $this->assertEquals('Jane Doe', $loadedUser->get('name'));
        $this->assertEquals('jane@example.com', $loadedUser->get('email'));
    }

    public function testLoadPostsForUser(): void
    {
        // Create a user
        $user = $this->userRepo->createAndSave([
            'name' => 'Bob Smith',
            'email' => 'bob@example.com'
        ]);

        // Create multiple posts for the user
        $post1 = $this->postsRepo->createAndSave([
            'user_id' => $user->getId(),
            'title' => 'Post 1',
            'content' => 'Content 1'
        ]);

        $post2 = $this->postsRepo->createAndSave([
            'user_id' => $user->getId(),
            'title' => 'Post 2',
            'content' => 'Content 2'
        ]);

        $post3 = $this->postsRepo->createAndSave([
            'user_id' => $user->getId(),
            'title' => 'Post 3',
            'content' => 'Content 3'
        ]);

        // Load posts for the user (OneToMany)
        $this->postsRepo->loadPostsForUser($user);

        // Verify posts are loaded
        $posts = $user->get('posts');
        $this->assertIsArray($posts);
        $this->assertCount(3, $posts);

        // Verify each post
        $postIds = array_map(fn($post) => $post->getId(), $posts);
        $this->assertContains($post1->getId(), $postIds);
        $this->assertContains($post2->getId(), $postIds);
        $this->assertContains($post3->getId(), $postIds);

        // Verify bidirectional link - each post should have the user
        foreach ($posts as $post) {
            $this->assertInstanceOf(Post::class, $post);
            $postUser = $post->get('user');
            $this->assertInstanceOf(User::class, $postUser);
            $this->assertEquals($user->getId(), $postUser->getId());
        }
    }

    public function testLoadPostsForUserWithNoPosts(): void
    {
        // Create a user with no posts
        $user = $this->userRepo->createAndSave([
            'name' => 'Empty User',
            'email' => 'empty@example.com'
        ]);

        // Load posts for the user
        $this->postsRepo->loadPostsForUser($user);

        // Verify empty array is set
        $posts = $user->get('posts');
        $this->assertIsArray($posts);
        $this->assertCount(0, $posts);
    }

    public function testBidirectionalRelationship(): void
    {
        // Create a user
        $user = $this->userRepo->createAndSave([
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com'
        ]);

        // Create posts
        $post1 = $this->postsRepo->createAndSave([
            'user_id' => $user->getId(),
            'title' => 'Bidirectional Test 1',
            'content' => 'Content 1'
        ]);

        $post2 = $this->postsRepo->createAndSave([
            'user_id' => $user->getId(),
            'title' => 'Bidirectional Test 2',
            'content' => 'Content 2'
        ]);

        // Load posts for user (this should set bidirectional link)
        $this->postsRepo->loadPostsForUser($user);

        // Verify user -> posts
        $posts = $user->get('posts');
        $this->assertCount(2, $posts);

        // Verify posts -> user (bidirectional link)
        foreach ($posts as $post) {
            $postUser = $post->get('user');
            $this->assertInstanceOf(User::class, $postUser);
            $this->assertEquals($user->getId(), $postUser->getId());
        }

        // Also test loading user for individual post
        $this->userRepo->loadUserForPost($post1);
        $loadedUser = $post1->get('user');
        $this->assertEquals($user->getId(), $loadedUser->getId());
    }

    public function testMultipleUsersWithPosts(): void
    {
        // Create first user with posts
        $user1 = $this->userRepo->createAndSave([
            'name' => 'User One',
            'email' => 'user1@example.com'
        ]);

        $post1 = $this->postsRepo->createAndSave([
            'user_id' => $user1->getId(),
            'title' => 'User 1 Post 1',
            'content' => 'Content'
        ]);

        $post2 = $this->postsRepo->createAndSave([
            'user_id' => $user1->getId(),
            'title' => 'User 1 Post 2',
            'content' => 'Content'
        ]);

        // Create second user with posts
        $user2 = $this->userRepo->createAndSave([
            'name' => 'User Two',
            'email' => 'user2@example.com'
        ]);

        $post3 = $this->postsRepo->createAndSave([
            'user_id' => $user2->getId(),
            'title' => 'User 2 Post 1',
            'content' => 'Content'
        ]);

        // Load posts for user1
        $this->postsRepo->loadPostsForUser($user1);
        $user1Posts = $user1->get('posts');
        $this->assertCount(2, $user1Posts);
        $this->assertEquals('User 1 Post 1', $user1Posts[0]->get('title'));
        $this->assertEquals('User 1 Post 2', $user1Posts[1]->get('title'));

        // Load posts for user2
        $this->postsRepo->loadPostsForUser($user2);
        $user2Posts = $user2->get('posts');
        $this->assertCount(1, $user2Posts);
        $this->assertEquals('User 2 Post 1', $user2Posts[0]->get('title'));

        // Verify posts belong to correct users
        foreach ($user1Posts as $post) {
            $this->assertEquals($user1->getId(), $post->get('user_id'));
        }
        foreach ($user2Posts as $post) {
            $this->assertEquals($user2->getId(), $post->get('user_id'));
        }
    }

    public function testLoadUserForPostWithNonExistentUser(): void
    {
        // Create a post with invalid user_id
        $this->pdo->exec("INSERT INTO posts (user_id, title, content) VALUES (999, 'Test', 'Content')");
        $postId = $this->pdo->lastInsertId();

        $post = $this->postsRepo->find($postId);
        $this->assertNotNull($post);

        // Try to load user (should handle gracefully)
        $this->userRepo->loadUserForPost($post);

        $loadedUser = $post->get('user');
        // Should be null if user doesn't exist
        $this->assertNull($loadedUser);
    }

    public function testUpdatePostAndReloadUser(): void
    {
        // Create user and post
        $user = $this->userRepo->createAndSave([
            'name' => 'Update Test',
            'email' => 'update@example.com'
        ]);

        $post = $this->postsRepo->createAndSave([
            'user_id' => $user->getId(),
            'title' => 'Original Title',
            'content' => 'Original content'
        ]);

        // Load user for post
        $this->userRepo->loadUserForPost($post);
        $this->assertInstanceOf(User::class, $post->get('user'));

        // Update post
        $post->set('title', 'Updated Title');
        $this->postsRepo->save($post);

        // Reload user (should still work)
        $this->userRepo->loadUserForPost($post);
        $loadedUser = $post->get('user');
        $this->assertInstanceOf(User::class, $loadedUser);
        $this->assertEquals($user->getId(), $loadedUser->getId());
    }
}

