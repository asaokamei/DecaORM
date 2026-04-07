<?php

namespace WScore\DecaORM\Tests\Fixtures\CustomLoader;

use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\Entity;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Trait\EntityTrait;

#[Table('tasks')]
#[Entity]
#[Repository(TaskRepository::class)]
class Task implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'task_id')]
    public ?int $task_id = null;
    
    #[Column(name: 'project_id')]
    public int $project_id = 0;

    #[Column(name: 'parent_id')]
    public ?int $parent_id = null;
    
    #[Column(name: 'user_id')]
    public int $user_id = 0;
    
    #[Column(name: 'title')]
    public string $title = '';

    #[BelongsTo(targetEntity: Task::class, foreignKey: 'parent_id', inversedBy: 'children')]
    public ?Task $parent = null;

    #[HasMany(targetEntity: Task::class, mappedBy: 'parent', orderBy: 'task_id ASC')]
    public ?EntityCollection $children = null;

    public function getId(): ?int
    {
        return $this->task_id;
    }
}

