<?php

namespace WScore\DecaORM\Tests\Users;

use Psr\Container\ContainerInterface;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\Trait\ManyToManyTrait;

class CourseRepository extends AbstractRepository
{
    use ManyToManyTrait;

    public function __construct(\PDO $db, ContainerInterface $container)
    {
        $this->db = $db;
        $this->container = $container;
        $this->hydrator = new AttributeHydrator(Course::class);
        $this->now = new \DateTimeImmutable();
    }
}

