<?php

namespace WScore\DecaORM\Tests\Fixtures\CustomLoader;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\RepositoryManager;

class InvalidProjectRepository extends AbstractRepository
{
    public function __construct(RepositoryManager $manager)
    {
        $this->setUpRepository($manager, null, InvalidProject::class);
    }

    public function create(array $data = []): InvalidProject
    {
        /** @var InvalidProject $project */
        $project = $this->createEntity($data);
        return $project;
    }
}

