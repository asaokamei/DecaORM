<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\RepositoryManager;
use WScore\DecaORM\Tests\CustomLoader\InvalidProject;
use WScore\DecaORM\Tests\CustomLoader\InvalidProjectRepository;
use WScore\DecaORM\Tests\CustomLoader\Project;
use WScore\DecaORM\Tests\CustomLoader\ProjectRepository;
use WScore\DecaORM\Tests\CustomLoader\ProjectRepositoryWithReturn;
use WScore\DecaORM\Tests\CustomLoader\Task;
use WScore\DecaORM\Tests\CustomLoader\TaskRepository;
use WScore\DecaORM\Tests\Users\Container;

class CustomLoaderTest extends TestCase
{
    private PDO $pdo;
    private ProjectRepository $projectRepo;
    private TaskRepository $taskRepo;

    protected function setUp(): void
    {
        // In-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create projects table
        $this->pdo->exec(
            "CREATE TABLE projects (
            project_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )"
        );

        // Create tasks table (with composite key scenario: project_id + user_id)
        $this->pdo->exec(
            "CREATE TABLE tasks (
            task_id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            FOREIGN KEY (project_id) REFERENCES projects(project_id)
        )"
        );

        // Clear cache before each test
        EntityCache::clear();

        $container = new Container();
        $manager = RepositoryManager::initialize($container);
        $this->projectRepo = new ProjectRepository($this->pdo, $manager);
        $this->taskRepo = new TaskRepository($this->pdo, $manager);
        $container->set(ProjectRepository::class, $this->projectRepo);
        $container->set(TaskRepository::class, $this->taskRepo);
    }

    public function testCustomLoaderWithSingleEntity(): void
    {
        // Create a project
        $project = $this->projectRepo->createAndSave([
            'name' => 'Project 1'
        ]);

        // Create tasks for the project
        $task1 = $this->taskRepo->createAndSave([
            'project_id' => $project->getId(),
            'user_id' => 1,
            'title' => 'Task 1'
        ]);

        $task2 = $this->taskRepo->createAndSave([
            'project_id' => $project->getId(),
            'user_id' => 2,
            'title' => 'Task 2'
        ]);

        // Clear cache and reload
        EntityCache::clear();
        $project = $this->projectRepo->findById($project->getId());

        // Load tasks using CustomLoader
        $tasks = $this->projectRepo->load($project, 'tasks');

        // Verify tasks are set on entity
        $projectTasks = $project->get('tasks');
        $this->assertIsArray($projectTasks);
        $this->assertCount(2, $projectTasks);
        $this->assertInstanceOf(Task::class, $projectTasks[0]);
        $this->assertInstanceOf(Task::class, $projectTasks[1]);
        $this->assertEquals('Task 1', $projectTasks[0]->get('title'));
        $this->assertEquals('Task 2', $projectTasks[1]->get('title'));
    }

    public function testCustomLoaderWithMultipleEntities(): void
    {
        // Create multiple projects
        $project1 = $this->projectRepo->createAndSave([
            'name' => 'Project 1'
        ]);

        $project2 = $this->projectRepo->createAndSave([
            'name' => 'Project 2'
        ]);

        // Create tasks for project1
        $task1 = $this->taskRepo->createAndSave([
            'project_id' => $project1->getId(),
            'user_id' => 1,
            'title' => 'Project 1 Task 1'
        ]);

        $task2 = $this->taskRepo->createAndSave([
            'project_id' => $project1->getId(),
            'user_id' => 2,
            'title' => 'Project 1 Task 2'
        ]);

        // Create tasks for project2
        $task3 = $this->taskRepo->createAndSave([
            'project_id' => $project2->getId(),
            'user_id' => 1,
            'title' => 'Project 2 Task 1'
        ]);

        // Clear cache and reload
        EntityCache::clear();
        $projects = [
            $this->projectRepo->findById($project1->getId()),
            $this->projectRepo->findById($project2->getId())
        ];

        // Batch load tasks using CustomLoader
        $tasks = $this->projectRepo->load($projects, 'tasks');

        // Verify tasks are set on entities
        $project1Tasks = $projects[0]->get('tasks');
        $this->assertIsArray($project1Tasks);
        $this->assertCount(2, $project1Tasks);
        $this->assertEquals('Project 1 Task 1', $project1Tasks[0]->get('title'));
        $this->assertEquals('Project 1 Task 2', $project1Tasks[1]->get('title'));

        $project2Tasks = $projects[1]->get('tasks');
        $this->assertIsArray($project2Tasks);
        $this->assertCount(1, $project2Tasks);
        $this->assertEquals('Project 2 Task 1', $project2Tasks[0]->get('title'));
    }

    public function testCustomLoaderWithNoRelations(): void
    {
        // Create a project without tasks
        $project = $this->projectRepo->createAndSave([
            'name' => 'Project 1'
        ]);

        // Clear cache and reload
        EntityCache::clear();
        $project = $this->projectRepo->findById($project->getId());

        // Load tasks using CustomLoader
        $tasks = $this->projectRepo->load($project, 'tasks');

        // Verify empty array is set on entity
        $projectTasks = $project->get('tasks');
        $this->assertIsArray($projectTasks);
        $this->assertCount(0, $projectTasks);
    }

    public function testCustomLoaderWithEmptyArray(): void
    {
        // Test with empty array - should return empty array without error
        $result = $this->projectRepo->load([], 'tasks');
        $this->assertCount(0, $result->getItems());
    }

    public function testCustomLoaderWithInvalidMethod(): void
    {
        $container = new Container();
        $manager = RepositoryManager::initialize($container);
        $invalidRepo = new InvalidProjectRepository($this->pdo, $manager);
        $container->set(InvalidProjectRepository::class, $invalidRepo);
        $container->set(TaskRepository::class, $this->taskRepo);

        // Create table
        $this->pdo->exec(
            "CREATE TABLE invalid_projects (
            project_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )"
        );

        $project = $invalidRepo->createAndSave([
            'name' => 'Project 1'
        ]);

        // Expect RuntimeException when method doesn't exist
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Loader method "nonExistentMethod" not found');

        $invalidRepo->load($project, 'tasks');
    }

    public function testCustomLoaderWithReturnValue(): void
    {
        $container = new Container();
        $manager = RepositoryManager::initialize($container);
        $repo = new ProjectRepositoryWithReturn($this->pdo, $manager);
        $container->set(ProjectRepositoryWithReturn::class, $repo);
        $container->set(TaskRepository::class, $this->taskRepo);

        // Create a project
        $project = $repo->createAndSave([
            'name' => 'Project 1'
        ]);

        // Create tasks
        $task1 = $this->taskRepo->createAndSave([
            'project_id' => $project->getId(),
            'user_id' => 1,
            'title' => 'Task 1'
        ]);

        // Clear cache and reload
        EntityCache::clear();
        $project = $repo->findById($project->getId());

        // Load tasks using CustomLoader
        $tasks = $repo->load($project, 'tasks');

        // Verify return value
        $this->assertCount(1, $tasks);
        $this->assertInstanceOf(Task::class, $tasks[0]);

        // Verify tasks are set on entity
        $projectTasks = $project->get('tasks');
        $this->assertIsArray($projectTasks);
        $this->assertCount(1, $projectTasks);
    }
}

