<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\CreatedAt;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Attribute\UpdatedAt;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\EntityTrait;

/**
 * Attributeを使ったUserエンティティのサンプル
 */
#[Table(name: 'users')]
class UserWithAttribute implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'user_id')]
    private ?int $user_id = null;

    #[Column(name: 'name')]
    private string $name;

    #[Column(name: 'email')]
    private string $email;

    #[CreatedAt(name: 'created_at')]
    private ?DateTimeImmutable $created_at = null;

    #[UpdatedAt(name: 'updated_at')]
    private ?DateTimeImmutable $updated_at = null;

    public function getId(): ?int
    {
        return $this->user_id;
    }

    public function setId(int|string $id): void
    {
        $this->user_id = (int) $id;
    }

    public function setCreatedAt(string|DateTimeImmutable $created_at): void
    {
        $this->setDateTimeProperty('created_at', $created_at);
    }

    public function setUpdatedAt(string|DateTimeImmutable $updated_at): void
    {
        $this->setDateTimeProperty('updated_at', $updated_at);
    }
}


