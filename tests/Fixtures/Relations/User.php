<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use DateTimeImmutable;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\CreatedAt;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Attribute\UpdatedAt;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'users')]
#[Repository(UserRepository::class)]
class User implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'user_id')]
    private ?string $id = null;

    #[Column(name: 'user_name')]
    private string $name = '';

    #[Column(name: 'email')]
    private string $email = '';

    #[CreatedAt(name: 'created_at')]
    private ?string $registered_at = null;

    #[UpdatedAt(name: 'updated_at')]
    private ?string $updated_at = null;

    /** @var Post[]|null */
    #[HasMany(targetEntity: Post::class, mappedBy: 'user', orderBy: 'created_at DESC')]
    private ?array $posts = null;

    #[HasOne(targetEntity: Profile::class, mappedBy: 'user')]
    private ?Profile $profile = null;

    /** @var Role[]|null */
    #[ManyToMany(
        targetEntity: Role::class,
        joinTable: 'user_role',
        foreignKey: 'user_id',
        inverseForeignKey: 'role_id'
    )]
    private ?array $roles = null;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPosts(): ?array
    {
        return $this->posts;
    }

    /**
     * @param Post[]|null $posts
     */
    public function setPosts(?array $posts): void
    {
        $this->posts = $posts;
        foreach ($posts ?? [] as $post) {
            $post->setUser($this);
        }
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): void
    {
        $this->profile = $profile;
    }

    public function addPost(Post $post): void
    {
        if (!$this->posts) {
            $this->posts = [];
        }
        if (in_array($post, $this->posts, true)) {
            return;
        }
        $this->posts[] = $post;
        if ($post->getUser() !== $this) {
            $post->setUser($this);
        }
    }

    public function removePost(Post $post): void
    {
        if ($post->getUser() === $this) {
            $post->setUser(null);
        }
        $this->posts = array_filter($this->posts ?? [], static function (Post $p) use ($post) {
            return $p->getId() !== $post->getId();
        });
    }
}
