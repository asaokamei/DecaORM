<?php

namespace WScore\DecaORM\Tests\Fixtures\CustomLoader;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\OrmManager;

class ProjectRepository extends AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, Project::class);
    }

    public function create(array $data = []): Project
    {
        /** @var Project $project */
        $project = $this->createEntity($data);
        return $project;
    }

    public function findTasks(EntityInterface|array $entities): void
    {
        $entities = is_array($entities) ? $entities : [$entities];
        $projectIds = array_filter(array_map(fn($e) => $e->getId(), $entities));

        if (empty($projectIds)) {
            foreach ($entities as $entity) {
                $entity->setRaw('tasks', []);
            }
            return;
        }

        $taskRepo = $this->getRepository(Task::class);
        $tasks = $taskRepo->sqlQuery()
            ->whereIn('project_id', $projectIds)
            ->getResult();

        $tasksByProjectId = [];
        foreach ($tasks as $task) {
            $projectId = $task->getRaw('project_id');
            if ($projectId !== null) {
                if (!isset($tasksByProjectId[$projectId])) {
                    $tasksByProjectId[$projectId] = [];
                }
                $tasksByProjectId[$projectId][] = $task;
            }
        }

        foreach ($entities as $entity) {
            $projectId = $entity->getId();
            $entity->setRaw('tasks', $tasksByProjectId[$projectId] ?? []);
        }
    }
}

