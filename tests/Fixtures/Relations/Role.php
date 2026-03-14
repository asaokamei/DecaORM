<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'roles')]
#[Repository(RoleRepository::class)]
class Role implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'role_id')]
    public ?string $id = null;

    #[Column(name: 'role_name')]
    public string $name = '';

    /** @var EntityCollection<User>|null */
    #[ManyToMany(
        targetEntity: User::class,
        joinTable: 'user_role',
        foreignKey: 'role_id',
        inverseForeignKey: 'user_id'
    )]
    public ?EntityCollection $users = null;

    public function getId(): ?int
    {
        return $this->id !== null ? (int) $this->id : null;
    }

    public function getUsers(): EntityCollection
    {
        return $this->load('users');
    }

    /**
     * @param EntityCollection<User>|null $users
     */
    public function setUsers(?EntityCollection $users): void
    {
        $this->associate('users', $users);
    }
}
