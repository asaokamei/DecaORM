<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Fixtures\Morph;

use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\Entity;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\MorphTo;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table('morph_comments')]
#[Entity]
#[Repository(MorphCommentRepository::class)]
class MorphComment implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'comment_id')]
    public ?int $comment_id = null;

    #[Column(name: 'body')]
    public string $body = '';

    #[Column(name: 'commentable_id')]
    public ?string $commentable_id = null;

    #[Column(name: 'commentable_type')]
    public ?string $commentable_type = null;

    #[MorphTo(
        foreignKey: 'commentable_id',
        typeColumn: 'commentable_type',
        typeMap: [
            'post' => MorphPost::class,
            'video' => MorphVideo::class,
        ],
        inversedBy: 'comments',
    )]
    public MorphPost|MorphVideo|null $commentable = null;

    public function getId(): ?int
    {
        return $this->comment_id;
    }
}
