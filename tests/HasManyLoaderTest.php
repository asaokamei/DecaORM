<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\Entity;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;

// Test entities for HasMany::loader
#[Table('projects')]
#[Entity]
#[Repository(ProjectWithLoaderRepository::class)]
class ProjectWithLoader implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'project_id')]
    public ?int $project_id = null;
    
    #[Column(name: 'name')]
    public string $name = '';

    #[HasMany(targetEntity: TaskWithDate::class, mappedBy: 'project', loader: 'findRecentTasks')]
    public array $recentTasks = [];

    public function getId(): ?int
    {
        return $this->project_id;
    }
}

#[Table('tasks')]
#[Entity]
#[Repository(TaskWithDateRepository::class)]
class TaskWithDate implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'task_id')]
    public ?int $task_id = null;
    
    #[Column(name: 'project_id')]
    public int $project_id = 0;
    
    #[Column(name: 'title')]
    public string $title = '';
    
    #[Column(name: 'created_at')]
    public string $created_at = '';

    #[BelongsTo(targetEntity: ProjectWithLoader::class, foreignKey: 'project_id')]
    public ?ProjectWithLoader $project = null;

    public function getId(): ?int
    {
        return $this->task_id;
    }
}

#[Repository(ProjectWithLoader::class)]
class ProjectWithLoaderRepository extends \WScore\DecaORM\AbstractRepository
{
    public function __construct(\WScore\DecaORM\RepositoryManager $manager)
    {
        $this->setUpRepository($manager, null, ProjectWithLoader::class);
    }

    /**
     * Loader method for recentTasks relation.
     * Returns tasks created within the last 7 days.
     * 
     * @param EntityInterface|array<EntityInterface> $projects Array of project IDs
     * @return EntityInterface[] Array of TaskWithDate entities
     */
    public function findRecentTasks(EntityInterface|array $projects): array
    {
        $projects = is_array($projects) ? $projects : [$projects];
        if (empty($projects)) {
            return [];
        }
        $projectIds = array_filter(array_map(fn($p) => $p->getId(), $projects));

        $taskRepo = $this->getRepository(TaskWithDate::class);
        // Calculate 7 days ago timestamp
        $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        // Load tasks created within the last 7 days
        // This demonstrates complex query conditions that loader can handle
        // SQLite can compare datetime strings directly if they're in ISO format (YYYY-MM-DD HH:MM:SS)
        $tasks = $taskRepo->sqlQuery()
            ->whereIn('project_id', $projectIds)
            ->where('created_at', $sevenDaysAgo, '>=')
            ->orderBy('created_at DESC')
            ->getResult();

        return $tasks;
    }
}

#[Repository(TaskWithDate::class)]
class TaskWithDateRepository extends \WScore\DecaORM\AbstractRepository
{
    public function __construct(\WScore\DecaORM\RepositoryManager $manager)
    {
        $this->setUpRepository($manager, null, TaskWithDate::class);
    }
}

class HasManyLoaderTest extends TestCase
{
    private PDO $pdo;
    private ProjectWithLoaderRepository $projectRepo;
    private TaskWithDateRepository $taskRepo;

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

        // Create tasks table with created_at
        $this->pdo->exec(
            "CREATE TABLE tasks (
            task_id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (project_id) REFERENCES projects(project_id)
        )"
        );

        // Clear cache before each test
        EntityCache::clear();

        $container = new TestContainer();
        $container->set(PDO::class, $this->pdo);
        $manager = \WScore\DecaORM\RepositoryManager::initialize($container);
        $this->projectRepo = new ProjectWithLoaderRepository($manager);
        $this->taskRepo = new TaskWithDateRepository($manager);
        $container->set(ProjectWithLoaderRepository::class, $this->projectRepo);
        $container->set(TaskWithDateRepository::class, $this->taskRepo);
    }

    public function testHasManyLoaderWithSingleEntity(): void
    {
        // Create a project
        $project = $this->projectRepo->createEntity([
            'name' => 'Project 1'
        ]);
        $this->projectRepo->save($project);

        // Create recent task (within 7 days) - use current timestamp format
        $recentTask = $this->taskRepo->createEntity([
            'project_id' => $project->getId(),
            'title' => 'Recent Task',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ]);
        $this->taskRepo->save($recentTask);

        // Create old task (more than 7 days ago)
        $oldTask = $this->taskRepo->createEntity([
            'project_id' => $project->getId(),
            'title' => 'Old Task',
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))
        ]);
        $this->taskRepo->save($oldTask);

        // Verify tasks were created
        $this->assertNotNull($recentTask->getId());
        $this->assertNotNull($oldTask->getId());

        // Clear cache and reload
        EntityCache::clear();
        $project = $this->projectRepo->findById($project->getId());

        // Load recentTasks using loader
        $tasks = $this->projectRepo->load($project, 'recentTasks');

        // Verify return value
        $this->assertCount(1, $tasks);
        $this->assertInstanceOf(TaskWithDate::class, $tasks[0]);
        $this->assertEquals('Recent Task', $tasks[0]->get('title'));

        // Verify recentTasks are set on entity
        $recentTasks = $project->get('recentTasks');
        $this->assertIsArray($recentTasks);
        $this->assertCount(1, $recentTasks);
        $this->assertEquals('Recent Task', $recentTasks[0]->get('title'));

        // Verify bidirectional link
        $this->assertSame($project, $recentTasks[0]->get('project'));
    }

    public function testHasManyLoaderWithMultipleEntities(): void
    {
        // Create multiple projects
        $project1 = $this->projectRepo->createEntity([
            'name' => 'Project 1'
        ]);
        $this->projectRepo->save($project1);

        $project2 = $this->projectRepo->createEntity([
            'name' => 'Project 2'
        ]);
        $this->projectRepo->save($project2);

        // Create recent tasks for project1
        $task1 = $this->taskRepo->createEntity([
            'project_id' => $project1->getId(),
            'title' => 'Project 1 Recent Task 1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]);
        $this->taskRepo->save($task1);

        $task2 = $this->taskRepo->createEntity([
            'project_id' => $project1->getId(),
            'title' => 'Project 1 Recent Task 2',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);
        $this->taskRepo->save($task2);

        // Create old task for project1 (should not be included)
        $oldTask1 = $this->taskRepo->createEntity([
            'project_id' => $project1->getId(),
            'title' => 'Project 1 Old Task',
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))
        ]);
        $this->taskRepo->save($oldTask1);

        // Create recent task for project2
        $task3 = $this->taskRepo->createEntity([
            'project_id' => $project2->getId(),
            'title' => 'Project 2 Recent Task',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ]);
        $this->taskRepo->save($task3);

        // Clear cache and reload
        EntityCache::clear();
        $projects = [
            $this->projectRepo->findById($project1->getId()),
            $this->projectRepo->findById($project2->getId())
        ];

        // Batch load recentTasks using loader
        $tasks = $this->projectRepo->load($projects, 'recentTasks');

        // Verify return value
        $this->assertCount(3, $tasks); // 2 from project1, 1 from project2

        // Verify recentTasks are set on entities
        $project1Tasks = $projects[0]->get('recentTasks');
        $this->assertIsArray($project1Tasks);
        $this->assertCount(2, $project1Tasks);
        $this->assertEquals('Project 1 Recent Task 2', $project1Tasks[0]->get('title')); // Ordered by created_at DESC
        $this->assertEquals('Project 1 Recent Task 1', $project1Tasks[1]->get('title'));

        $project2Tasks = $projects[1]->get('recentTasks');
        $this->assertIsArray($project2Tasks);
        $this->assertCount(1, $project2Tasks);
        $this->assertEquals('Project 2 Recent Task', $project2Tasks[0]->get('title'));

        // Verify bidirectional links
        foreach ($project1Tasks as $task) {
            $this->assertSame($projects[0], $task->get('project'));
        }
        foreach ($project2Tasks as $task) {
            $this->assertSame($projects[1], $task->get('project'));
        }
    }

    public function testHasManyLoaderWithNoRecentTasks(): void
    {
        // Create a project
        $project = $this->projectRepo->createEntity([
            'name' => 'Project 1'
        ]);
        $this->projectRepo->save($project);

        // Create only old tasks (more than 7 days ago)
        $oldTask1 = $this->taskRepo->createEntity([
            'project_id' => $project->getId(),
            'title' => 'Old Task 1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))
        ]);
        $this->taskRepo->save($oldTask1);

        $oldTask2 = $this->taskRepo->createEntity([
            'project_id' => $project->getId(),
            'title' => 'Old Task 2',
            'created_at' => date('Y-m-d H:i:s', strtotime('-20 days'))
        ]);
        $this->taskRepo->save($oldTask2);

        // Clear cache and reload
        EntityCache::clear();
        $project = $this->projectRepo->findById($project->getId());

        // Load recentTasks using loader
        $tasks = $this->projectRepo->load($project, 'recentTasks');

        // Verify return value
        $this->assertCount(0, $tasks);

        // Verify empty array is set on entity
        $recentTasks = $project->get('recentTasks');
        $this->assertIsArray($recentTasks);
        $this->assertCount(0, $recentTasks);
    }

    public function testHasManyLoaderWithNoTasksAtAll(): void
    {
        // Create a project without any tasks
        $project = $this->projectRepo->createEntity([
            'name' => 'Project 1'
        ]);
        $this->projectRepo->save($project);

        // Clear cache and reload
        EntityCache::clear();
        $project = $this->projectRepo->findById($project->getId());

        // Load recentTasks using loader
        $tasks = $this->projectRepo->load($project, 'recentTasks');

        // Verify return value
        $this->assertCount(0, $tasks);

        // Verify empty array is set on entity
        $recentTasks = $project->get('recentTasks');
        $this->assertIsArray($recentTasks);
        $this->assertCount(0, $recentTasks);
    }

    public function testHasManyLoaderFiltersCorrectly(): void
    {
        // Create a project
        $project = $this->projectRepo->createEntity([
            'name' => 'Project 1'
        ]);
        $this->projectRepo->save($project);

        // Create tasks with various dates
        $veryRecent = $this->taskRepo->createEntity([
            'project_id' => $project->getId(),
            'title' => 'Very Recent',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);
        $this->taskRepo->save($veryRecent);

        $recent = $this->taskRepo->createEntity([
            'project_id' => $project->getId(),
            'title' => 'Recent',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ]);
        $this->taskRepo->save($recent);

        $justOld = $this->taskRepo->createEntity([
            'project_id' => $project->getId(),
            'title' => 'Just Old',
            'created_at' => date('Y-m-d H:i:s', strtotime('-8 days')) // Just over 7 days
        ]);
        $this->taskRepo->save($justOld);

        $veryOld = $this->taskRepo->createEntity([
            'project_id' => $project->getId(),
            'title' => 'Very Old',
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 days'))
        ]);
        $this->taskRepo->save($veryOld);

        // Clear cache and reload
        EntityCache::clear();
        $project = $this->projectRepo->findById($project->getId());

        // Load recentTasks using loader
        $tasks = $this->projectRepo->load($project, 'recentTasks');

        // Verify only recent tasks (within 7 days) are returned
        $this->assertCount(2, $tasks);
        
        $titles = $tasks->getValues('title');
        $this->assertContains('Very Recent', $titles);
        $this->assertContains('Recent', $titles);
        $this->assertNotContains('Just Old', $titles);
        $this->assertNotContains('Very Old', $titles);

        // Verify tasks are ordered by created_at DESC
        $this->assertEquals('Very Recent', $tasks[0]->get('title'));
        $this->assertEquals('Recent', $tasks[1]->get('title'));
    }
}

