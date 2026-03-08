<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\RepositoryManager;

/**
 * @extends AbstractRepository<Post>
 */
class PostRepository extends AbstractRepository
{
    public function __construct(RepositoryManager $manager)
    {
        $this->setUpRepository($manager, null, Post::class);
    }

    public function create(User $user, array $data): Post
    {
        $data['user_id'] = $user->getId();
        return $this->createAndSave($data);
    }

    public function loadUser(Post $post): void
    {
        $this->load($post, 'user');
    }

    public function loadComments(Post $post): void
    {
        $this->load($post, 'comments');
    }
}
