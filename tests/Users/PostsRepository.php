<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use Psr\Container\ContainerInterface;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\AbstractRepository;

/**
 * @extends AbstractRepository<Post>
 */
class PostsRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, ContainerInterface $container = null, HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(Post::class);
        $this->now = new DateTimeImmutable();
        $this->container = $container;
    }

    public function create(User $user, array $data): Post
    {
        $data['user_id'] = $user->getId();
        return $this->createAndSave($data);
    }

    public function fillUser(Post $post): void
    {
        $this->fill($post, 'user');
    }
}

