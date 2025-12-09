<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use Psr\Container\ContainerInterface;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\AbstractRepository;

/**
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, ContainerInterface $container = null, HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(User::class);
        $this->now = new DateTimeImmutable();
        $this->container = $container;
    }

    public function fillPosts(User $user): void
    {
        $this->fill($user, 'posts');
    }
}
