<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\RepositoryTrait;
use WScore\DecaORM\SampleRepository;

/**
 * @extends SampleRepository<User>
 */
class UserRepository extends SampleRepository
{
    use RepositoryTrait;

    public function __construct(PDO $pdo, HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(User::class);
        $this->now = new DateTimeImmutable();
    }

    /**
     * PostにUserを読み込む（ManyToOne）
     */
    public function loadUserForPost(Post $post): void
    {
        $this->fillParentEntity($post, 'user', 'user_id');
    }
}
