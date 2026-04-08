<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Tests\Fixtures\CustomLoader\Project;
use WScore\DecaORM\Tests\Fixtures\CustomLoader\ProjectRepository;
use WScore\DecaORM\Tests\Fixtures\CustomLoader\Task;
use WScore\DecaORM\Tests\Fixtures\CustomLoader\TaskRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;
use WScore\DecaORM\Tests\Fixtures\TaskHierarchy\TaskHierarchyFixture;
use WScore\DecaORM\Tests\Support\DbConnection;

/**
 * Self-referential Task hierarchy ({@code parent_id} → {@code tasks.task_id}).
 */
class TaskSelfReferentialRelationTest extends TestCase
{
    private PDO $pdo;
    private ProjectRepository $projectRepo;
    private TaskRepository $taskRepo;
    private OrmManager $manager;

    protected function setUp(): void
    {
        $this->pdo = DbConnection::get();
        TaskHierarchyFixture::loadProjectsAndTasksSchema($this->pdo);

        $container = new TestContainer();
        $container->set(PDO::class, $this->pdo);
        $this->manager = OrmManager::initialize($container);
        $this->projectRepo = new ProjectRepository($this->manager);
        $this->taskRepo = new TaskRepository($this->manager);
        $container->set(ProjectRepository::class, $this->projectRepo);
        $container->set(TaskRepository::class, $this->taskRepo);
    }

    public function testBelongsToLoadsParentTask(): void
    {
        $project = $this->projectRepo->create(['name' => 'P1']);
        $this->projectRepo->save($project);

        $root = $this->taskRepo->create([
            'project_id' => $project->getId(),
            'user_id' => 1,
            'title' => 'Root',
        ]);
        $this->taskRepo->save($root);

        $child = $this->taskRepo->create([
            'project_id' => $project->getId(),
            'parent_id' => $root->getId(),
            'user_id' => 1,
            'title' => 'Child',
        ]);
        $this->taskRepo->save($child);

        $this->manager->getEntityCache()->clear();
        $child = $this->taskRepo->findById($child->getId());
        $this->assertNotNull($child);

        $this->taskRepo->load($child, 'parent');
        $parent = $child->getRaw('parent');
        $this->assertInstanceOf(Task::class, $parent);
        $this->assertEquals($root->getId(), $parent->getId());
        $this->assertEquals('Root', $parent->getRaw('title'));
    }

    public function testHasManyLoadsChildTasks(): void
    {
        $project = $this->projectRepo->create(['name' => 'P1']);
        $this->projectRepo->save($project);

        $root = $this->taskRepo->create([
            'project_id' => $project->getId(),
            'user_id' => 1,
            'title' => 'Root',
        ]);
        $this->taskRepo->save($root);

        $c1 = $this->taskRepo->create([
            'project_id' => $project->getId(),
            'parent_id' => $root->getId(),
            'user_id' => 1,
            'title' => 'Child 1',
        ]);
        $this->taskRepo->save($c1);

        $c2 = $this->taskRepo->create([
            'project_id' => $project->getId(),
            'parent_id' => $root->getId(),
            'user_id' => 1,
            'title' => 'Child 2',
        ]);
        $this->taskRepo->save($c2);

        $this->manager->getEntityCache()->clear();
        $root = $this->taskRepo->findById($root->getId());
        $this->assertNotNull($root);

        $this->taskRepo->load($root, 'children');
        $children = $root->getRaw('children');
        $this->assertInstanceOf(EntityCollection::class, $children);
        $this->assertCount(2, $children);
        $this->assertEquals('Child 1', $children[0]->getRaw('title'));
        $this->assertEquals('Child 2', $children[1]->getRaw('title'));
        $this->assertSame($root, $children[0]->getRaw('parent'));
        $this->assertSame($root, $children[1]->getRaw('parent'));
    }

    public function testRootTaskHasEmptyChildren(): void
    {
        $project = $this->projectRepo->create(['name' => 'P1']);
        $this->projectRepo->save($project);

        $root = $this->taskRepo->create([
            'project_id' => $project->getId(),
            'user_id' => 1,
            'title' => 'Root',
        ]);
        $this->taskRepo->save($root);

        $this->manager->getEntityCache()->clear();
        $root = $this->taskRepo->findById($root->getId());
        $this->taskRepo->load($root, 'children');

        $children = $root->getRaw('children');
        $this->assertInstanceOf(EntityCollection::class, $children);
        $this->assertCount(0, $children);
    }
}
