<?php

namespace WScore\DecaORM\Tests\Fixtures\CustomLoader;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\RepositoryManager;

class TaskRepository extends AbstractRepository
{
    public function __construct(RepositoryManager $manager)
    {
        $this->setUpRepository($manager, null, Task::class);
    }

    public function create(array $data = []): Task
    {
        /** @var Task $task */
        $task = $this->createEntity($data);
        return $task;
    }
}

