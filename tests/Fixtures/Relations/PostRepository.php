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

    public function create(User|array $user, array $data = []): Post
    {
        if ($user instanceof User) {
            $data['user_id'] = $user->getId();
        } else {
            $data = $user;
        }

        /** @var Post $post */
        $post = $this->createEntity($data);
        return $post;
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
