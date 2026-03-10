<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\Contacts\EntityInterface;
use WScore\DecaORM\Tests\Fixtures\Relations\Comment;
use WScore\DecaORM\Tests\Fixtures\Relations\Post;
use WScore\DecaORM\Tests\Fixtures\Relations\PostRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\RelationsFixture;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;

class OneToManyTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private PostRepository $postsRepo;

    protected function setUp(): void
    {
        $fixture = RelationsFixture::create();
        $this->pdo = $fixture->pdo;
        $this->userRepo = $fixture->users;
        $this->postsRepo = $fixture->posts;
    }

    public function createAndSaveUser(string|int $name): User|EntityInterface|null
    {
        $mail = str_replace(' ', '.', (string) $name);
        $user = $this->userRepo->create([
            'name' => 'User'.$name,
            'email' => 'user.'.$mail.'@example.com',
        ]);
        $this->userRepo->save($user);
        return $user;
    }

    public function createAndSavePost(User $user, string $title): Post|EntityInterface|null
    {
        $post = $this->postsRepo->create($user, [
            'title' => "User {$user->getId()} Post {$title}",
            'content' => 'Contents U{$user->getId()}/P{$title}',
        ]);
        $this->postsRepo->save($post);
        return $post;
    }

    public function createPost(User $user, string $title): Post|EntityInterface|null
    {
        return $this->postsRepo->createEntity([
            'user_id' => $user->getId(),
            'title' => "User {$user->getId()} Post {$title}",
            'content' => 'Contents U{$user->getId()}/P{$title}',
        ]);
    }
    public function testCreateUserAndPosts(): void
    {
        // Create a user
        $user = $this->userRepo->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        $this->userRepo->save($user);

        $this->assertNotNull($user->getId());
        $this->assertEquals('John Doe', $user->get('name'));

        // Create posts for the user
        $post1 = $this->postsRepo->create([
            'user_id' => $user->getId(),
            'title' => 'First Post',
            'content' => 'This is the first post'
        ]);
        $this->postsRepo->save($post1);

        $post2 = $this->postsRepo->create([
            'user_id' => $user->getId(),
            'title' => 'Second Post',
            'content' => 'This is the second post'
        ]);
        $this->postsRepo->save($post2);

        $this->assertNotNull($post1->getId());
        $this->assertNotNull($post2->getId());
        $this->assertEquals('First Post', $post1->get('title'));
        $this->assertEquals('Second Post', $post2->get('title'));
    }

    public function testLoadUserForPost(): void
    {
        // Create a user
        $user = $this->userRepo->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);
        $this->userRepo->save($user);

        // Create a post
        $post = $this->postsRepo->create([
            'user_id' => $user->getId(),
            'title' => 'Test Post',
            'content' => 'Test content'
        ]);
        $this->postsRepo->save($post);

        // Load user for the post (BelongsTo)
        $this->postsRepo->loadUser($post);

        // Verify the user is loaded
        $loadedUser = $post->get('user');
        $this->assertInstanceOf(User::class, $loadedUser);
        $this->assertEquals($user->getId(), $loadedUser->getId());
        $this->assertEquals('Jane Doe', $loadedUser->getName());
        $this->assertEquals('jane@example.com', $loadedUser->getEmail());
    }

    public function testLoadPostsForUser(): void
    {
        // Create a user
        $user = $this->createAndSaveUser(1);

        // Create multiple posts for the user
        $post1 = $this->createAndSavePost($user, 1);
        $post2 = $this->createAndSavePost($user, 2);
        $post3 = $this->createAndSavePost($user, 3);

        // Load posts for the user (HasMany)
        $this->userRepo->loadPosts($user);

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
        $user = $this->userRepo->create([
            'name' => 'Empty User',
            'email' => 'empty@example.com'
        ]);
        $this->userRepo->save($user);

        // Load posts for the user
        $this->userRepo->loadPosts($user);

        // Verify empty array is set
        $posts = $user->get('posts');
        $this->assertIsArray($posts);
        $this->assertCount(0, $posts);
    }

    public function testBidirectionalRelationship(): void
    {
        // Create a user
        $user = $this->createAndSaveUser(1);
        $post1 = $this->createAndSavePost($user, 'Bidirectional 1');
        $post2 = $this->createAndSavePost($user, 'Bidirectional 2');

        // Load posts for user (this should set bidirectional link)
        $this->userRepo->loadPosts($user);

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
        $this->postsRepo->loadUser($post1);
        $loadedUser = $post1->get('user');
        $this->assertEquals($user->getId(), $loadedUser->getId());
    }

    public function testMultipleUsersWithPosts(): void
    {
        // Create first user with posts
        $user1 = $this->createAndSaveUser(1);
        $user2 = $this->createAndSaveUser(2);

        $this->createAndSavePost($user1, 1);
        $this->createAndSavePost($user1, 2);
        $this->createAndSavePost($user2, 3);

        // Load posts for user1
        $this->userRepo->loadPosts($user1);
        $user1Posts = $user1->get('posts');
        $this->assertCount(2, $user1Posts);
        $this->assertEquals('User 1 Post 1', $user1Posts[0]->get('title'));
        $this->assertEquals('User 1 Post 2', $user1Posts[1]->get('title'));

        // Load posts for user2
        $this->userRepo->loadPosts($user2);
        $user2Posts = $user2->get('posts');
        $this->assertCount(1, $user2Posts);
        $this->assertEquals('User 2 Post 3', $user2Posts[0]->get('title'));

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

        $post = $this->postsRepo->findById($postId);
        $this->assertNotNull($post);

        // Try to load user (should handle gracefully)
        $this->postsRepo->loadUser($post);

        $loadedUser = $post->get('user');
        // Should be null if user doesn't exist
        $this->assertNull($loadedUser);
    }

    public function testUpdatePostAndReloadUser(): void
    {
        // Create user and post
        $user = $this->userRepo->create([
            'name' => 'Update Test',
            'email' => 'update@example.com'
        ]);
        $this->userRepo->save($user);

        $post = $this->postsRepo->create([
            'user_id' => $user->getId(),
            'title' => 'Original Title',
            'content' => 'Original content'
        ]);
        $this->postsRepo->save($post);

        // Load user for post
        $this->postsRepo->loadUser($post);
        $this->assertInstanceOf(User::class, $post->get('user'));

        // Update post
        $post->set('title', 'Updated Title');
        $this->postsRepo->save($post);

        // Reload user (should still work)
        $this->postsRepo->loadUser($post);
        $loadedUser = $post->get('user');
        $this->assertInstanceOf(User::class, $loadedUser);
        $this->assertEquals($user->getId(), $loadedUser->getId());
    }

    public function testMovePostBetweenUsers()
    {
        // Create first user with posts
        $user1 = $this->createAndSaveUser(1);
        $user2 = $this->createAndSaveUser(2);

        $this->createAndSavePost($user1, 1);
        $this->createAndSavePost($user1, 2);
        $this->createAndSavePost($user2, 3);

        // check saved entities.
        EntityCache::clear();
        $user1 = $this->userRepo->findById(1);
        $user2 = $this->userRepo->findById(2);
        $this->userRepo->loadPosts($user1);
        $this->userRepo->loadPosts($user2);
        $this->assertCount(2, $user1->get('posts'));
        $this->assertCount(1, $user2->get('posts'));

        // move post from user1 to user2.
        $postMoved = $user1->getPosts()[0];
        $postMoved->setTitle('Moved from User 1 to User 2');
        $postMoved->setUser($user2);
        // check post is moved from original user as well.
        $this->assertCount(1, $user1->get('posts'));
        $this->assertCount(2, $user2->get('posts'));
        $this->postsRepo->save($postMoved);

        // check saved entities.
        EntityCache::clear();
        $user1 = $this->userRepo->findById(1);
        $user2 = $this->userRepo->findById(2);
        $this->userRepo->loadPosts($user1);
        $this->userRepo->loadPosts($user2);
        $this->assertCount(1, $user1->get('posts'));
        $this->assertCount(2, $user2->get('posts'));

        // verify post moved to user2.
        foreach ($user2->get('posts') as $post) {
            if ($post->getId() == $postMoved->getId()) {
                $this->assertEquals('Moved from User 1 to User 2', $post->getTitle());
            }
        }
    }

    public function testSetPosts()
    {
        $user1 = $this->createAndSaveUser(1);
        $user2 = $this->createAndSaveUser(2);

        $posts = [];
        $posts[] = $this->createPost($user1, 1);
        $posts[] = $this->createPost($user1, 2);
        $posts[] = $this->createPost($user1, 3);

        $user2->setPosts($posts);
        $this->postsRepo->save($user2);
        foreach ($posts as $post) {
            $this->assertEquals($user2->getId(), $post->getUser()->getId());
            $this->postsRepo->save($post);
        }

        EntityCache::clear();
        $user = $this->userRepo->findById($user2->getId());
        $this->userRepo->loadPosts($user);
        $this->assertCount(3, $user->get('posts'));
    }

    public function testLoadCommentsForPost(): void
    {
        $user = $this->createAndSaveUser(1);
        $post = $this->createAndSavePost($user, 'with-comments');

        $this->pdo->exec(
            "INSERT INTO comments (post_id, body, created_at) VALUES ({$post->getId()}, 'Comment 1', datetime('now'))"
        );
        $this->pdo->exec(
            "INSERT INTO comments (post_id, body, created_at) VALUES ({$post->getId()}, 'Comment 2', datetime('now', '-1 second'))"
        );

        EntityCache::clear();
        $post = $this->postsRepo->findById($post->getId());
        $this->postsRepo->loadComments($post);

        $comments = $post->get('comments');
        $this->assertIsArray($comments);
        $this->assertCount(2, $comments);
        $this->assertInstanceOf(Comment::class, $comments[0]);
    }
}

