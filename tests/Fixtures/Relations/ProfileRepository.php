<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\OrmManager;

class ProfileRepository extends AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, Profile::class);
    }

    public function create(array $data = []): Profile
    {
        /** @var Profile $profile */
        $profile = $this->createEntity($data);
        return $profile;
    }
}
