<?php

namespace WScore\DecaORM\Tests\Fixtures\CustomLoader;

use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryManager;

class ProjectRepositoryWithReturn extends AbstractRepository
{
    public function __construct(RepositoryManager $manager)
    {
        $this->setUpRepository($manager, null, Project::class);
    }

    public function findTasks(EntityInterface|array $entities): array
    {
        $entities = is_array($entities) ? $entities : [$entities];
        $projectIds = array_filter(array_map(fn($e) => $e->getId(), $entities));

        if (empty($projectIds)) {
            foreach ($entities as $entity) {
                $entity->set('tasks', []);
            }
            return [];
        }

        $taskRepo = $this->getRepository(Task::class);
        $tasks = $taskRepo->sqlQuery()
            ->whereIn('project_id', $projectIds)
            ->getResult();

        $tasksByProjectId = [];
        foreach ($tasks as $task) {
            $projectId = $task->get('project_id');
            if ($projectId !== null) {
                if (!isset($tasksByProjectId[$projectId])) {
                    $tasksByProjectId[$projectId] = [];
                }
                $tasksByProjectId[$projectId][] = $task;
            }
        }

        foreach ($entities as $entity) {
            $projectId = $entity->getId();
            $entity->set('tasks', $tasksByProjectId[$projectId] ?? []);
        }

        return $tasks;
    }
}

