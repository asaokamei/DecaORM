<?php

namespace WScore\DecaORM\Tests\CustomLoader;

use PDO;
use Psr\Container\ContainerInterface;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\HydratorInterface;

class ProjectRepository extends AbstractRepository
{
    public function __construct(PDO $pdo, ?ContainerInterface $container = null, ?HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(Project::class);
        $this->container = $container;
    }

    public function findTasks(EntityInterface|array $entities): void
    {
        $entities = is_array($entities) ? $entities : [$entities];
        $projectIds = array_filter(array_map(fn($e) => $e->getId(), $entities));

        if (empty($projectIds)) {
            foreach ($entities as $entity) {
                $entity->set('tasks', []);
            }
            return;
        }

        $taskRepo = $this->getRepository(Task::class);
        // Simulate composite key query (project_id + user_id)
        // In real scenario, this would be a complex query
        $tasks = $taskRepo->sqlQuery()
            ->whereIn('project_id', $projectIds)
            ->getResult();

        // Group by project_id and set on entities
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

        // Set tasks on each entity
        foreach ($entities as $entity) {
            $projectId = $entity->getId();
            $entity->set('tasks', $tasksByProjectId[$projectId] ?? []);
        }
    }
}

