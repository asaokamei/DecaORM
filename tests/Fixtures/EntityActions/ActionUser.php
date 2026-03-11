<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Fixtures\EntityActions;

use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Trait\EntityActionsTrait;
use WScore\DecaORM\Trait\EntityTrait;

#[Table(name: 'action_users')]
#[Repository('WScore\\DecaORM\\Tests\\Fixtures\\EntityActions\\ActionUserRepository')]
final class ActionUser implements EntityInterface
{
    use EntityTrait;
    use EntityActionsTrait;

    #[Column(name: 'user_id')]
    #[Id]
    #[GeneratedValue]
    private ?int $id = null;

    #[Column(name: 'user_name')]
    private ?string $name = null;

    public function getId(): int|string|null
    {
        return $this->id;
    }
}

