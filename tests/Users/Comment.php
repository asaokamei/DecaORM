<?php

namespace WScore\DecaORM\Tests\Users;

use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'comments')]
#[Repository(CommentsRepository::class)]
class Comment implements EntityInterface
{
    use EntityTrait;
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'comment_id')]
    private ?int $id = null;
    #[Column(name: 'post_id')]
    private ?int $post_id;
    #[Column(name: 'comment')]
    private ?string $comment;

    #[BelongsTo(targetEntity: Post::class, foreignKey: 'post_id')]
    private ?Post $post = null;

    public function getId(): null|int|string
    {
        return $this->id;
    }
    public function getPost(): ?Post
    {
        return $this->post;
    }
    public function setPost(Post $post): void
    {
        $this->post = $post;
        $this->post_id = $post->getId();
        $post->addComment($this);
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }
}