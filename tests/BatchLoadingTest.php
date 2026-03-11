<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\Tests\Fixtures\Relations\Post;
use WScore\DecaORM\Tests\Fixtures\Relations\PostRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\Profile;
use WScore\DecaORM\Tests\Fixtures\Relations\ProfileRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\RelationsFixture;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;

class BatchLoadingTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private PostRepository $postsRepo;
    private ProfileRepository $profileRepo;

    protected function setUp(): void
    {
        $fixture = RelationsFixture::create();
        $this->pdo = $fixture->pdo;
        $this->userRepo = $fixture->users;
        $this->postsRepo = $fixture->posts;
        $this->profileRepo = $fixture->profiles;
    }

    public function testBatchLoadHasMany(): void
    {
        // Create multiple users with posts
        $user1 = $this->userRepo->create([
            'name' => 'User One',
            'email' => 'user1@example.com'
        ]);
        $this->userRepo->save($user1);

        $user2 = $this->userRepo->create([
            'name' => 'User Two',
            'email' => 'user2@example.com'
        ]);
        $this->userRepo->save($user2);

        // Create posts for user1
        $post1 = $this->postsRepo->create($user1, [
            'title' => 'User 1 Post 1',
            'content' => 'Content 1'
        ]);
        $this->postsRepo->save($post1);
        $post2 = $this->postsRepo->create($user1, [
            'title' => 'User 1 Post 2',
            'content' => 'Content 2'
        ]);
        $this->postsRepo->save($post2);

        // Create posts for user2
        $post3 = $this->postsRepo->create($user2, [
            'title' => 'User 2 Post 1',
            'content' => 'Content 3'
        ]);
        $this->postsRepo->save($post3);

        // Clear cache and reload
        EntityCache::clear();
        $users = [
            $this->userRepo->findById($user1->getId()),
            $this->userRepo->findById($user2->getId())
        ];

        // Batch load posts for all users
        $posts = $this->userRepo->load($users, 'posts');

        // Verify return value
        $this->assertIsArray($posts->getEntities());
        $this->assertEquals(3, $posts->count());

        // Verify posts are set on entities
        $user1Posts = $users[0]->getRaw('posts');
        $this->assertInstanceOf(EntityCollection::class, $user1Posts);
        $this->assertCount(2, $user1Posts);

        $user2Posts = $users[1]->getRaw('posts');
        $this->assertInstanceOf(EntityCollection::class, $user2Posts);
        $this->assertCount(1, $user2Posts);

        // Verify bidirectional links
        foreach ($user1Posts as $post) {
            $this->assertInstanceOf(Post::class, $post);
            $this->assertEquals($user1->getId(), $post->getRaw('user_id'));
        }
    }

    public function testBatchLoadHasOne(): void
    {
        // Create multiple users with profiles
        $user1 = $this->userRepo->create([
            'name' => 'User One',
            'email' => 'user1@example.com'
        ]);
        $this->userRepo->save($user1);

        $user2 = $this->userRepo->create([
            'name' => 'User Two',
            'email' => 'user2@example.com'
        ]);
        $this->userRepo->save($user2);

        // Create profiles
        $profile1 = $this->profileRepo->create([
            'id' => $user1->getId(),
            'nickname' => 'Nickname 1'
        ]);
        $this->profileRepo->save($profile1);

        $profile2 = $this->profileRepo->create([
            'id' => $user2->getId(),
            'nickname' => 'Nickname 2'
        ]);
        $this->profileRepo->save($profile2);

        // Clear cache and reload
        EntityCache::clear();
        $users = [
            $this->userRepo->findById($user1->getId()),
            $this->userRepo->findById($user2->getId())
        ];

        // Batch load profiles for all users
        $profiles = $this->userRepo->load($users, 'profile');

        // Verify return value
        $this->assertIsArray($profiles->getEntities());
        $this->assertCount(2, $profiles->getEntities());

        // Verify profiles are set on entities
        $user1Profile = $users[0]->getRaw('profile');
        $this->assertInstanceOf(Profile::class, $user1Profile);
        $this->assertEquals('Nickname 1', $user1Profile->getRaw('nickname'));

        $user2Profile = $users[1]->getRaw('profile');
        $this->assertInstanceOf(Profile::class, $user2Profile);
        $this->assertEquals('Nickname 2', $user2Profile->getRaw('nickname'));
    }

    public function testBatchLoadBelongsTo(): void
    {
        // Create users
        $user1 = $this->userRepo->create([
            'name' => 'User One',
            'email' => 'user1@example.com'
        ]);
        $this->userRepo->save($user1);

        $user2 = $this->userRepo->create([
            'name' => 'User Two',
            'email' => 'user2@example.com'
        ]);
        $this->userRepo->save($user2);

        // Create posts
        $post1 = $this->postsRepo->create($user1, [
            'title' => 'Post 1',
            'content' => 'Content 1'
        ]);
        $this->postsRepo->save($post1);

        $post2 = $this->postsRepo->create($user2, [
            'title' => 'Post 2',
            'content' => 'Content 2'
        ]);
        $this->postsRepo->save($post2);

        // Clear cache and reload
        EntityCache::clear();
        $posts = [
            $this->postsRepo->findById($post1->getId()),
            $this->postsRepo->findById($post2->getId())
        ];

        // Batch load users for all posts
        $users = $this->postsRepo->load($posts, 'user');

        // Verify return value
        $this->assertIsArray($users->getEntities());
        $this->assertCount(2, $users->getEntities());

        // Verify users are set on entities
        $post1User = $posts[0]->getRaw('user');
        $this->assertInstanceOf(User::class, $post1User);
        $this->assertEquals($user1->getId(), $post1User->getId());

        $post2User = $posts[1]->getRaw('user');
        $this->assertInstanceOf(User::class, $post2User);
        $this->assertEquals($user2->getId(), $post2User->getId());
    }

    public function testBatchLoadWithEmptyArray(): void
    {
        $result = $this->userRepo->load([], 'posts');
        $this->assertIsArray($result->getEntities());
        $this->assertCount(0, $result->getEntities());
    }

    public function testBatchLoadWithNoRelations(): void
    {
        // Create users without posts
        $user1 = $this->userRepo->create([
            'name' => 'User One',
            'email' => 'user1@example.com'
        ]);
        $this->userRepo->save($user1);

        $user2 = $this->userRepo->create([
            'name' => 'User Two',
            'email' => 'user2@example.com'
        ]);
        $this->userRepo->save($user2);

        // Clear cache and reload
        EntityCache::clear();
        $users = [
            $this->userRepo->findById($user1->getId()),
            $this->userRepo->findById($user2->getId())
        ];

        // Batch load posts (should return empty array)
        $posts = $this->userRepo->load($users, 'posts');

        // Verify return value
        $this->assertIsArray($posts->getEntities());
        $this->assertCount(0, $posts->getEntities());

        // Verify empty arrays are set on entities
        $user1Posts = $users[0]->getRaw('posts');
        $this->assertInstanceOf(EntityCollection::class, $user1Posts);
        $this->assertCount(0, $user1Posts);

        $user2Posts = $users[1]->getRaw('posts');
        $this->assertInstanceOf(EntityCollection::class, $user2Posts);
        $this->assertCount(0, $user2Posts);
    }

    public function testBatchLoadChaining(): void
    {
        // Create users with posts
        $user1 = $this->userRepo->create([
            'name' => 'User One',
            'email' => 'user1@example.com'
        ]);
        $this->userRepo->save($user1);

        $user2 = $this->userRepo->create([
            'name' => 'User Two',
            'email' => 'user2@example.com'
        ]);
        $this->userRepo->save($user2);

        // Create posts
        $post1 = $this->postsRepo->create($user1, [
            'title' => 'Post 1',
            'content' => 'Content 1'
        ]);
        $this->postsRepo->save($post1);

        $post2 = $this->postsRepo->create($user2, [
            'title' => 'Post 2',
            'content' => 'Content 2'
        ]);
        $this->postsRepo->save($post2);

        // Clear cache and reload
        EntityCache::clear();
        $users = [
            $this->userRepo->findById($user1->getId()),
            $this->userRepo->findById($user2->getId())
        ];

        // Chain: load posts, then load users for those posts
        $posts = $this->userRepo->load($users, 'posts');
        $this->assertCount(2, $posts);

        // Verify posts are set on users
        $this->assertCount(1, $users[0]->getRaw('posts'));
        $this->assertCount(1, $users[1]->getRaw('posts'));

        // Now load users for posts (this is the chaining use case)
        $loadedUsers = $posts->load('user');
        $this->assertCount(2, $loadedUsers);

        // Verify users are set on posts
        $this->assertInstanceOf(User::class, $posts[0]->getRaw('user'));
        $this->assertInstanceOf(User::class, $posts[1]->getRaw('user'));
    }

    public function testSingleEntityStillWorks(): void
    {
        // Create user with posts
        $user = $this->userRepo->create([
            'name' => 'User One',
            'email' => 'user1@example.com'
        ]);
        $this->userRepo->save($user);

        $post1 = $this->postsRepo->create($user, [
            'title' => 'Post 1',
            'content' => 'Content 1'
        ]);
        $this->postsRepo->save($post1);

        $post2 = $this->postsRepo->create($user, [
            'title' => 'Post 2',
            'content' => 'Content 2'
        ]);
        $this->postsRepo->save($post2);

        // Clear cache and reload
        EntityCache::clear();
        $user = $this->userRepo->findById($user->getId());

        // Single entity fill (existing behavior)
        $posts = $this->userRepo->load($user, 'posts');

        // Verify return value
        $this->assertIsArray($posts->getEntities());
        $this->assertCount(2, $posts->getEntities());

        // Verify posts are set on entity
        $userPosts = $user->getRaw('posts');
        $this->assertInstanceOf(EntityCollection::class, $userPosts);
        $this->assertCount(2, $userPosts);
    }
}

