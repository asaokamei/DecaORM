<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use DateTimeImmutable;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\CreatedAt;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Attribute\UpdatedAt;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'posts')]
#[Repository(PostRepository::class)]
class Post implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'post_id')]
    private ?string $post_id = null;

    #[Column(name: 'user_id')]
    private ?string $user_id = null;

    #[Column(name: 'title')]
    private string $title = '';

    #[Column(name: 'content')]
    private string $content = '';

    #[CreatedAt(name: 'created_at')]
    private ?string $created_at = null;

    #[UpdatedAt(name: 'updated_at')]
    private ?string $updated_at = null;

    #[BelongsTo(targetEntity: User::class, foreignKey: 'user_id', inversedBy: 'posts')]
    private ?User $user = null;

    /** @var EntityCollection<Comment>|null */
    #[HasMany(targetEntity: Comment::class, mappedBy: 'post', orderBy: 'created_at DESC')]
    private ?EntityCollection $comments = null;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        if ($user === $this->user) {
            return;
        }
        $originalUser = $this->user;
        $this->user = $user;
        $this->user_id = $user?->getId() !== null ? (string) $user->getId() : null;
        if ($originalUser !== null) {
            $originalUser->removePost($this);
        }
        if ($user !== null) {
            $user->addPost($this);
        }
    }
}
