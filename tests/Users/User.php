<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\CreatedAt;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Attribute\UpdatedAt;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\EntityTrait;

/**
 * Attributeを使ったUserエンティティのサンプル
 */
#[Table(name: 'users')]
#[Repository(UserRepository::class)]
class User implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'user_id')]
    public ?string $id = null;

    #[Column(name: 'user_name')]
    public string $name = '';

    #[Column(name: 'email')]
    public string $email = '';

    #[CreatedAt(name: 'created_at')]
    public ?string $registered_at = null;

    #[UpdatedAt(name: 'updated_at')]
    public ?string $updated_at = null;

    /** @var Post[]|null */
    #[HasMany(targetEntity: Post::class, mappedBy: 'user', orderBy: 'created_at DESC')]
    public ?array $posts = null;

    #[HasOne(targetEntity: Profile::class, mappedBy: 'user')]
    public ?Profile $profile = null;

    public function getId(): ?int
    {
        return $this->id !== null ? (int) $this->id : null;
    }

    public function getRegisteredAt(): ?DateTimeImmutable
    {
        return $this->registered_at !== null ? new DateTimeImmutable($this->registered_at) : null;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updated_at !== null ? new DateTimeImmutable($this->updated_at) : null;
    }
}


