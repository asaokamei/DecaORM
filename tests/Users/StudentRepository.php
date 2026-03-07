<?php

namespace WScore\DecaORM\Tests\Users;

use Psr\Container\ContainerInterface;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\RepositoryManager;
use WScore\DecaORM\Trait\ManyToManyTrait;

class StudentRepository extends AbstractRepository
{
    use ManyToManyTrait;

    public function __construct(\PDO $db, RepositoryManager $manager)
    {
        $this->db = $db;
        $this->manager = $manager;
        $this->hydrator = new AttributeHydrator(Student::class);
        $this->now = new \DateTimeImmutable();
    }
}

