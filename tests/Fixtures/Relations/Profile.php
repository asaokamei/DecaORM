<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contacts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'profiles')]
#[Repository(ProfileRepository::class)]
class Profile implements EntityInterface
{
    use EntityTrait;

    #[Column(name: 'profile_id')]
    #[Id]
    private int $id;

    #[Column(name: 'nickname')]
    private string $nickname;

    #[BelongsToOne(targetEntity: User::class, foreignKey: 'id', inversedBy: 'profile')]
    private ?User $user;

    public function getId(): null|int|string
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        if ($user) {
            $this->id = $user->getId();
        }
        $this->user = $user;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setNickname(string $nickname): void
    {
        $this->nickname = $nickname;
    }
}
