<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\RepositoryManager;

class ProfileRepository extends AbstractRepository
{
    public function __construct(RepositoryManager $manager)
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
