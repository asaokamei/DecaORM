<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use Psr\Container\ContainerInterface;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\RepositoryManager;

/**
 * @extends AbstractRepository<Post>
 */
class PostsRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, ?RepositoryManager $manager = null, ?HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(Post::class);
        $this->now = new DateTimeImmutable();
        $this->manager = $manager;
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
}

