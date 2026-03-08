<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\RepositoryManager;
use WScore\DecaORM\Trait\ManyToManyTrait;

class RoleRepository extends AbstractRepository
{
    use ManyToManyTrait;

    public function __construct(RepositoryManager $manager)
    {
        $this->setUpRepository($manager, null, Role::class);
    }
}
