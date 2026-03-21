<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Fixtures\Morph;

use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\Entity;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Trait\EntityTrait;

#[Table('morph_posts')]
#[Entity]
#[Repository(MorphPostRepository::class)]
class MorphPost implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'post_id')]
    public ?int $post_id = null;

    #[Column(name: 'title')]
    public string $title = '';

    #[HasMany(targetEntity: MorphComment::class, mappedBy: 'commentable')]
    public ?EntityCollection $comments = null;

    #[HasOne(targetEntity: MorphThumbnail::class, mappedBy: 'thumbnailable')]
    public ?MorphThumbnail $thumbnail = null;

    public function getId(): ?int
    {
        return $this->post_id;
    }
}
