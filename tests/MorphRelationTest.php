<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Collection;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Tests\Fixtures\Morph\MorphComment;
use WScore\DecaORM\Tests\Fixtures\Morph\MorphCommentRepository;
use WScore\DecaORM\Tests\Fixtures\Morph\MorphPost;
use WScore\DecaORM\Tests\Fixtures\Morph\MorphPostRepository;
use WScore\DecaORM\Tests\Fixtures\Morph\MorphSchema;
use WScore\DecaORM\Tests\Fixtures\Morph\MorphThumbnail;
use WScore\DecaORM\Tests\Fixtures\Morph\MorphThumbnailRepository;
use WScore\DecaORM\Tests\Fixtures\Morph\MorphVideo;
use WScore\DecaORM\Tests\Fixtures\Morph\MorphVideoRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;
use WScore\DecaORM\Tests\Support\DbConnection;

require_once __DIR__ . '/../vendor/autoload.php';

class MorphRelationTest extends TestCase
{
    private PDO $pdo;
    private MorphPostRepository $postRepo;
    private MorphVideoRepository $videoRepo;
    private MorphCommentRepository $commentRepo;
    private MorphThumbnailRepository $thumbnailRepo;

    protected function setUp(): void
    {
        $this->pdo = DbConnection::get();
        foreach (['morph_comments', 'morph_thumbnails', 'morph_posts', 'morph_videos'] as $t) {
            $this->pdo->exec(MorphSchema::loadTable($t));
        }

        $container = new TestContainer();
        $container->set(PDO::class, $this->pdo);
        $manager = OrmManager::initialize($container);

        $this->postRepo = new MorphPostRepository($manager);
        $this->videoRepo = new MorphVideoRepository($manager);
        $this->commentRepo = new MorphCommentRepository($manager);
        $this->thumbnailRepo = new MorphThumbnailRepository($manager);

        $container->set(MorphPostRepository::class, $this->postRepo);
        $container->set(MorphVideoRepository::class, $this->videoRepo);
        $container->set(MorphCommentRepository::class, $this->commentRepo);
        $container->set(MorphThumbnailRepository::class, $this->thumbnailRepo);
    }

    public function testMorphToLoadsPostParent(): void
    {
        $this->pdo->exec("INSERT INTO morph_posts (title) VALUES ('Hello')");
        $postId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec(
            "INSERT INTO morph_comments (body, commentable_id, commentable_type) VALUES ('Nice', {$postId}, 'post')"
        );

        $comment = $this->commentRepo->findById(1);
        $this->assertInstanceOf(MorphComment::class, $comment);

        $loaded = $this->commentRepo->load($comment, 'commentable');
        $this->assertInstanceOf(Collection::class, $loaded);
        $this->assertCount(1, $loaded);
        $parent = $loaded->first();
        $this->assertInstanceOf(MorphPost::class, $parent);
        $this->assertSame($postId, $parent->getId());
        $this->assertSame('Hello', $parent->title);
    }

    public function testMorphToLoadsVideoParent(): void
    {
        $this->pdo->exec("INSERT INTO morph_videos (title) VALUES ('Clip')");
        $videoId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec(
            "INSERT INTO morph_comments (body, commentable_id, commentable_type) VALUES ('Cool', {$videoId}, 'video')"
        );

        $comment = $this->commentRepo->findById(1);
        $loaded = $this->commentRepo->load($comment, 'commentable');
        $parent = $loaded->first();
        $this->assertInstanceOf(MorphVideo::class, $parent);
        $this->assertSame($videoId, $parent->getId());
    }

    public function testHasManyInverseUsesMorphColumns(): void
    {
        $this->pdo->exec("INSERT INTO morph_posts (title) VALUES ('P1')");
        $p1 = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO morph_posts (title) VALUES ('P2')");
        $p2 = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO morph_videos (title) VALUES ('V1')");
        $v1 = (int) $this->pdo->lastInsertId();

        $this->pdo->exec(
            "INSERT INTO morph_comments (body, commentable_id, commentable_type) VALUES ('a', {$p1}, 'post')"
        );
        $this->pdo->exec(
            "INSERT INTO morph_comments (body, commentable_id, commentable_type) VALUES ('b', {$p2}, 'post')"
        );
        $this->pdo->exec(
            "INSERT INTO morph_comments (body, commentable_id, commentable_type) VALUES ('c', {$v1}, 'video')"
        );

        $post1 = $this->postRepo->findById($p1);
        $this->assertInstanceOf(MorphPost::class, $post1);
        $this->postRepo->load($post1, 'comments');
        $comments = $post1->comments;
        $this->assertNotNull($comments);
        $this->assertCount(1, $comments);
        $this->assertSame('a', $comments->first()->body);

        $post2 = $this->postRepo->findById($p2);
        $this->postRepo->load($post2, 'comments');
        $this->assertCount(1, $post2->comments);
        $this->assertSame('b', $post2->comments->first()->body);

        $video = $this->videoRepo->findById($v1);
        $this->videoRepo->load($video, 'comments');
        $this->assertCount(1, $video->comments);
        $this->assertSame('c', $video->comments->first()->body);
    }

    public function testMorphToOneAndHasOne(): void
    {
        $this->pdo->exec("INSERT INTO morph_posts (title) VALUES ('With thumb')");
        $postId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec(
            "INSERT INTO morph_thumbnails (url, thumbnailable_id, thumbnailable_type) VALUES ('/t.jpg', {$postId}, 'post')"
        );

        $post = $this->postRepo->findById($postId);
        $this->postRepo->load($post, 'thumbnail');
        $this->assertInstanceOf(MorphThumbnail::class, $post->thumbnail);
        $this->assertSame('/t.jpg', $post->thumbnail->url);

        $thumb = $this->thumbnailRepo->findById(1);
        $this->thumbnailRepo->load($thumb, 'thumbnailable');
        $parent = $thumb->thumbnailable;
        $this->assertInstanceOf(MorphPost::class, $parent);
        $this->assertSame($postId, $parent->getId());
    }

    public function testMorphToBatchLoadParents(): void
    {
        $this->pdo->exec("INSERT INTO morph_posts (title) VALUES ('A')");
        $p = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO morph_videos (title) VALUES ('B')");
        $v = (int) $this->pdo->lastInsertId();

        $this->pdo->exec(
            "INSERT INTO morph_comments (body, commentable_id, commentable_type) VALUES ('x', {$p}, 'post')"
        );
        $this->pdo->exec(
            "INSERT INTO morph_comments (body, commentable_id, commentable_type) VALUES ('y', {$v}, 'video')"
        );

        $c1 = $this->commentRepo->findById(1);
        $c2 = $this->commentRepo->findById(2);
        $this->commentRepo->load([$c1, $c2], 'commentable');

        $this->assertInstanceOf(MorphPost::class, $c1->commentable);
        $this->assertInstanceOf(MorphVideo::class, $c2->commentable);
    }
}
