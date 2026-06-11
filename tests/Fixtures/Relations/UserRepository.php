<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Trait\ManyToManyTrait;

/**
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository
{
    use ManyToManyTrait;

    private ?string $roleNamePrefixFilter = null;

    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, User::class);
    }

    public function create(array $data = []): User
    {
        /** @var User $user */
        $user = $this->createEntity($data);
        return $user;
    }

    public function loadPosts(User $user): void
    {
        $this->load($user, 'posts');
    }

    public function loadProfile(User $user): void
    {
        $this->load($user, 'profile');
    }

    public function setRoleNamePrefixFilter(?string $prefix): void
    {
        $this->roleNamePrefixFilter = $prefix;
    }

    public function applyRoleFilter(Query $query, EntityInterface|EntityCollection $users): void
    {
        if ($this->roleNamePrefixFilter === null || $this->roleNamePrefixFilter == '') {
            return;
        }
        $query->where('role_name', $this->roleNamePrefixFilter . '%', 'LIKE');
    }
}
