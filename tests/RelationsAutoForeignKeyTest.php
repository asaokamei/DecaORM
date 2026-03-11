<?php

use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\Tests\Fixtures\Relations\Post;
use WScore\DecaORM\Tests\Fixtures\Relations\PostRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\Profile;
use WScore\DecaORM\Tests\Fixtures\Relations\ProfileRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\RelationsFixture;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;

class RelationsAutoForeignKeyTest extends TestCase
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

    public function testHasMany_childrenForeignKeysFilledOnParentInsert(): void
    {
        // Prepare a parent with in-memory children (no user_id set yet)
        /** @var User $user */
        $user = $this->userRepo->createEntity([
            'name' => 'Parent User',
            'email' => 'parent@example.com',
        ]);

        $post1 = new Post();
        $post1->setRaw('title', 'p1');
        $post1->setRaw('content', 'c1');

        $post2 = new Post();
        $post2->setRaw('title', 'p2');
        $post2->setRaw('content', 'c2');

        // Assign children via HasMany property
        $user->setRaw('posts', [$post1, $post2]);

        // Save parent (insert). This should trigger filling children's back-reference and FK
        $this->userRepo->save($user);

        $this->assertNotNull($user->getId(), 'Parent should have an ID after insert');

        // Verify children got user FK and back-reference set
        foreach ([$post1, $post2] as $i => $post) {
            $msg = "post#" . ($i + 1);
            // user_id may be stored as string; compare with == to ignore type
            $this->assertEquals($user->getId(), $post->getRaw('user_id'), $msg . ' should get user_id filled');
            $this->assertSame($user, $post->getRaw('user'), $msg . ' should get user back-reference filled');
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
        $user->setRaw('profile', $profile);

        // Save parent (insert). This should trigger filling child's back-reference and FK (profile.id)
        $this->userRepo->save($user);

        $this->assertNotNull($user->getId(), 'Parent should have an ID after insert');

        // Profile has private fields; verify via accessor
        $this->assertSame($user->getId(), $profile->getId(), 'Profile primary key should match user id');
        $this->assertSame($user, $profile->getUser(), 'Profile back-reference should be set to user');
    }
}
