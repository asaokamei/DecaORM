<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use DateTimeImmutable;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\CreatedAt;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Attribute\UpdatedAt;
use WScore\DecaORM\Contacts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'comments')]
#[Repository(CommentRepository::class)]
class Comment implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'comment_id')]
    private ?string $comment_id = null;

    #[Column(name: 'post_id')]
    private ?string $post_id = null;

    #[Column(name: 'body')]
    private string $body = '';

    #[CreatedAt(name: 'created_at')]
    private ?string $created_at = null;

    #[UpdatedAt(name: 'updated_at')]
    private ?string $updated_at = null;

    #[BelongsTo(targetEntity: Post::class, foreignKey: 'post_id', inversedBy: 'comments')]
    private ?Post $post = null;

    public function getId(): ?int
    {
        return $this->comment_id !== null ? (int) $this->comment_id : null;
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
