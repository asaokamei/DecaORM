<?php

namespace WScore\DecaORM\Tests\Fixtures\CustomLoader;

use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\CustomLoader;
use WScore\DecaORM\Attribute\Entity;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

#[Table('invalid_projects')]
#[Entity]
#[Repository(InvalidProjectRepository::class)]
class InvalidProject implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'project_id')]
    public ?int $project_id = null;
    
    #[Column(name: 'name')]
    public string $name = '';

    #[CustomLoader(targetEntity: Task::class, method: 'nonExistentMethod')]
    public array $tasks = [];

    public function getId(): ?int
    {
        return $this->project_id;
    }
}

