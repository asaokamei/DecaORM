<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\RepositoryTrait;
use WScore\DecaORM\AbstractRepository;

/**
 * @extends AbstractRepository<Post>
 */
class PostsRepository extends AbstractRepository
{
    use RepositoryTrait;

    public function __construct(PDO $pdo, HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(Post::class);
        $this->now = new DateTimeImmutable();
    }

    /**
     * UserにPostsを読み込む（OneToMany）
     * UserRepositoryから呼び出されることを想定
     */
    public function loadPostsForUser(User $user): void
    {
        $this->fillChildEntities($user, 'posts', 'user_id', 'user');
    }
}

