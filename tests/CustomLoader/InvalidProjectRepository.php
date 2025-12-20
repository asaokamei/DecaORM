<?php

namespace WScore\DecaORM\Tests\CustomLoader;

use PDO;
use Psr\Container\ContainerInterface;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\HydratorInterface;

class InvalidProjectRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, ?ContainerInterface $container = null, ?HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(InvalidProject::class);
        $this->container = $container;
    }
}

