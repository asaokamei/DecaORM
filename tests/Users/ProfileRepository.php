<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use Psr\Container\ContainerInterface;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\RepositoryManager;

class ProfileRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, RepositoryManager $manager)
    {
        $this->db = $pdo;
        $this->hydrator = new AttributeHydrator(Profile::class);
        $this->manager = $manager;
        $this->now = new DateTimeImmutable('now');
    }
}