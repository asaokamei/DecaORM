<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Fixtures\Morph;

use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\Entity;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\MorphToOne;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table('morph_thumbnails')]
#[Entity]
#[Repository(MorphThumbnailRepository::class)]
class MorphThumbnail implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'thumbnail_id')]
    public ?int $thumbnail_id = null;

    #[Column(name: 'url')]
    public string $url = '';

    #[Column(name: 'thumbnailable_id')]
    public ?string $thumbnailable_id = null;

    #[Column(name: 'thumbnailable_type')]
    public ?string $thumbnailable_type = null;

    #[MorphToOne(
        foreignKey: 'thumbnailable_id',
        typeColumn: 'thumbnailable_type',
        typeMap: [
            'post' => MorphPost::class,
        ],
        inversedBy: 'thumbnail',
    )]
    public ?MorphPost $thumbnailable = null;

    public function getId(): ?int
    {
        return $this->thumbnail_id;
    }
}
