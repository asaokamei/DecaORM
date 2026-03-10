<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Fixtures\EntityActions;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\RepositoryManager;

/**
 * @extends AbstractRepository<ActionUser>
 */
class ActionUserRepository extends AbstractRepository
{
    public function __construct(RepositoryManager $manager)
    {
        $this->setUpRepository($manager, null, ActionUser::class);
    }

    public function create(array $data = []): ActionUser
    {
        /** @var ActionUser $user */
        $user = $this->createEntity($data);
        return $user;
    }
}

