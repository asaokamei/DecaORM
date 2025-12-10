<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use Psr\Container\ContainerInterface;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\AttributeHydrator;

class ProfileRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, ContainerInterface $container)
    {
        $this->db = $pdo;
        $this->hydrator = new AttributeHydrator(Profile::class);
        $this->container = $container;
        $this->now = new DateTimeImmutable('now');
    }
}