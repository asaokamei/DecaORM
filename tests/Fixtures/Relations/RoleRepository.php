<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Trait\ManyToManyTrait;

class RoleRepository extends AbstractRepository
{
    use ManyToManyTrait;

    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, Role::class);
    }

    public function create(array $data = []): Role
    {
        /** @var Role $role */
        $role = $this->createEntity($data);
        return $role;
    }
}
