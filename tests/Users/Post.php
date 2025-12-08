<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\CreatedAt;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Attribute\UpdatedAt;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\EntityTrait;

/**
 * Postエンティティ - Userに対するOneToManyの子エンティティ
 */
#[Table(name: 'posts')]
#[Repository(PostsRepository::class)]
class Post implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'post_id')]
    public ?string $post_id = null;

    #[Column(name: 'user_id')]
    public ?string $user_id = null;

    #[Column(name: 'title')]
    public string $title = '';

    #[Column(name: 'content')]
    public string $content = '';

    #[CreatedAt(name: 'created_at')]
    public ?string $created_at = null;

    #[UpdatedAt(name: 'updated_at')]
    public ?string $updated_at = null;

    /** @var User|null */
    #[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
    public ?User $user = null;

    public function getId(): ?int
    {
        return $this->post_id !== null ? (int) $this->post_id : null;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->created_at !== null ? new DateTimeImmutable($this->created_at) : null;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updated_at !== null ? new DateTimeImmutable($this->updated_at) : null;
    }
}

