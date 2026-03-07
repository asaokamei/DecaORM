<?php
namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryManager;
use WScore\DecaORM\Tests\Users\Container;
use WScore\DecaORM\Tests\Users\Post;
use WScore\DecaORM\Tests\Users\PostsRepository;
use WScore\DecaORM\Tests\Users\User;
use WScore\DecaORM\Tests\Users\UserRepository;
use WScore\DecaORM\Tests\Users\Comment;
use WScore\DecaORM\Tests\Users\CommentsRepository;

class EntityHandlerTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private PostsRepository $postsRepo;
    private CommentsRepository $commentsRepo;

    protected function setUp(): void
    {
        // In-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create users table
        $sql = file_get_contents(__DIR__ . '/Users/users.sql');
        $this->pdo->exec($sql);

        // Create posts table
        $sql = file_get_contents(__DIR__ . '/Users/posts.sql');
        $this->pdo->exec($sql);

        // Create comments table
        $sql = file_get_contents(__DIR__ . '/Users/comments.sql');
        $this->pdo->exec($sql);

        // Clear cache before each test
        EntityCache::clear();

        $container = new Container();
        $manager = RepositoryManager::initialize($container);
        $this->userRepo = new UserRepository($this->pdo, $manager);
        $this->postsRepo = new PostsRepository($this->pdo, $manager);
        $this->commentsRepo = new CommentsRepository($this->pdo, $manager);
        $container->set(UserRepository::class, $this->userRepo);
        $container->set(PostsRepository::class, $this->postsRepo);
        $container->set(CommentsRepository::class, $this->commentsRepo);
    }

    public function createAndSaveUser(string|int $name): User|EntityInterface|null
    {
        $mail = str_replace(' ', '.', (string)$name);
        return $this->userRepo->createAndSave([
                                                  'name' => 'User' . $name,
                                                  'email' => 'user.' . $mail . '@example.com',
                                              ]);
    }

    public function createAndSavePost(User $user, string $title): Post|EntityInterface|null
    {
        return $this->postsRepo->createAndSave([
                                                   'user_id' => $user->getId(),
                                                   'title' => "User {$user->getId()} Post {$title}",
                                                   'content' => 'Contents U{$user->getId()}/P{$title}',
                                               ]);
    }

    public function createAndSaveComment(Post $post, string $content): Comment|EntityInterface|null
    {
        return $this->commentsRepo->createAndSave([
                                                   'post_id' => $post->getId(),
                                                   'comment' => "Comment P{$post->getId()}/{$content}",
                                               ]);
    }

    public function testReplicate(): void
    {
        // Create a user
        $user = $this->createAndSaveUser('U1');
        $post1 = $this->createAndSavePost($user, 'P1');
        $post2 = $this->createAndSavePost($user, 'P2');
        $this->createAndSaveComment($post1, 'C11');
        $this->createAndSaveComment($post1, 'C12');
        $this->createAndSaveComment($post1, 'C13');
        $this->createAndSaveComment($post2, 'C21');
        $this->createAndSaveComment($post2, 'C22');

        // test relation loader
        EntityCache::clear();
        $replicator = $this->userRepo->makeHandler($user);
        $replicator->load('posts.comments');
        $posts = $user->get('posts');
        $this->assertCount(2, $posts);
        $comments = $posts[0]->get('comments');
        $this->assertCount(3, $comments);

        $replicated = $replicator->replicate();
        $entity = $replicated->getEntity();
        $posts = $entity->get('posts');
        $this->assertCount(2, $posts);

        $post1 = $posts[0];
        $this->assertNull($post1->getId());
        $this->assertNull($posts[1]->getId());
        $this->assertEquals($entity, $post1->getUser());

        $this->assertCount(3, $post1->get('comments'));
        $comment1 = $post1->get('comments')[0];
        $this->assertNull($comment1->getId());
        $this->assertEquals($post1, $comment1->getPost());

        // test save
        $replicated->save();
        $newUser = $replicator->getEntity();
        $this->assertNotEquals($entity->getId(), $newUser->getId());
        $this->assertNotNull($newUser->getId());
        $this->assertNotNull($post1->getId());
        $this->assertNotNull($comment1->getId());
    }
}