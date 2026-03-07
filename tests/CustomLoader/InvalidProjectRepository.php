<?php

namespace WScore\DecaORM\Tests\CustomLoader;

use PDO;
use Psr\Container\ContainerInterface;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\RepositoryManager;

class InvalidProjectRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, ?RepositoryManager $manager, ?HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(InvalidProject::class);
        $this->manager = $manager;
    }
}

