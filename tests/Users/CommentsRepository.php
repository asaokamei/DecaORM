<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use Psr\Container\ContainerInterface;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\RepositoryManager;

/**
 * @extends AbstractRepository<Post>
 */
class CommentsRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, RepositoryManager $manager, ?HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(Comment::class);
        $this->now = new DateTimeImmutable();
        $this->manager = $manager;
    }

    public function create(Post $post, array $data): Comment|EntityInterface
    {
        $data['post_id'] = $post->getId();
        return $this->createEntity($data);
    }
}

