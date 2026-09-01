<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\Entity;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\Tests\Fixtures\Relations\TestContainer;
use WScore\DecaORM\Tests\Support\DbConnection;
use WScore\DecaORM\Tests\Support\SchemaLoader;
use WScore\DecaORM\Trait\EntityTrait;

#[Table('projects')]
#[Entity]
#[Repository(ScopeProjectRepository::class)]
class ScopeProject implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'project_id')]
    public ?int $project_id = null;

    #[Column(name: 'name')]
    public string $name = '';

    #[HasMany(targetEntity: ScopeTask::class, mappedBy: 'project', targetScope: 'activeOnly')]
    public ?EntityCollection $activeTasks = null;

    #[HasMany(targetEntity: ScopeTask::class, mappedBy: 'project', targetScope: 'titlePrefixedByProjectName')]
    public ?EntityCollection $prefixedTasks = null;

    #[HasMany(targetEntity: ScopeTask::class, mappedBy: 'project', sourceFilter: 'filterUrgentTasks')]
    public ?EntityCollection $urgentTasks = null;

    #[HasMany(targetEntity: ScopeTask::class, mappedBy: 'project', apply: 'filterUrgentTasks')]
    public ?EntityCollection $legacyUrgentTasks = null;

    #[HasMany(targetEntity: ScopeTask::class, mappedBy: 'project', sourceFilter: 'filterUrgentTasks', targetScope: 'activeOnly')]
    public ?EntityCollection $activeUrgentTasks = null;

    #[HasMany(targetEntity: ScopeTask::class, mappedBy: 'project', targetScope: 'nonExistentScope')]
    public ?EntityCollection $invalidScopeTasks = null;

    #[HasMany(targetEntity: ScopeTask::class, mappedBy: 'project', sourceFilter: 'nonExistentFilter')]
    public ?EntityCollection $invalidFilterTasks = null;

    public function getId(): ?int
    {
        return $this->project_id;
    }
}

#[Table('tasks')]
#[Entity]
#[Repository(ScopeTaskRepository::class)]
class ScopeTask implements EntityInterface
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
    public ?string $created_at = null;

    #[BelongsTo(targetEntity: ScopeProject::class, foreignKey: 'project_id')]
    public ?ScopeProject $project = null;

    #[BelongsTo(targetEntity: ScopeProject::class, foreignKey: 'project_id', targetScope: 'activeProjects')]
    public ?ScopeProject $activeProject = null;

    #[BelongsTo(targetEntity: ScopeProject::class, foreignKey: 'project_id', sourceFilter: 'onlySpecialProjects')]
    public ?ScopeProject $specialProject = null;

    #[BelongsTo(targetEntity: ScopeProject::class, foreignKey: 'project_id', apply: 'onlySpecialProjects')]
    public ?ScopeProject $legacySpecialProject = null;

    #[BelongsTo(targetEntity: ScopeProject::class, foreignKey: 'project_id', sourceFilter: 'onlySpecialProjects', targetScope: 'activeProjects')]
    public ?ScopeProject $activeSpecialProject = null;

    #[BelongsTo(targetEntity: ScopeProject::class, foreignKey: 'project_id', targetScope: 'nonExistentScope')]
    public ?ScopeProject $invalidScopeProject = null;

    #[BelongsTo(targetEntity: ScopeProject::class, foreignKey: 'project_id', sourceFilter: 'nonExistentFilter')]
    public ?ScopeProject $invalidFilterProject = null;

    public function getId(): ?int
    {
        return $this->task_id;
    }
}

#[Table('users')]
#[Entity]
#[Repository(ScopeUserRepository::class)]
class ScopeUser implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'user_id')]
    public ?int $id = null;

    #[Column(name: 'user_name')]
    public ?string $name = null;

    #[Column(name: 'email')]
    public ?string $email = null;

    #[Column(name: 'created_at')]
    public ?string $created_at = null;

    #[Column(name: 'updated_at')]
    public ?string $updated_at = null;

    #[HasOne(targetEntity: ScopeProfile::class, mappedBy: 'user', targetScope: 'verifiedOnly')]
    public ?ScopeProfile $verifiedProfile = null;

    #[HasOne(targetEntity: ScopeProfile::class, mappedBy: 'user', sourceFilter: 'filterAdminProfile')]
    public ?ScopeProfile $adminProfile = null;

    #[ManyToMany(
        targetEntity: ScopeRole::class,
        joinTable: 'user_role',
        foreignKey: 'user_id',
        inverseForeignKey: 'role_id',
        targetScope: 'adminRolesOnly'
    )]
    public ?EntityCollection $adminRoles = null;

    #[ManyToMany(
        targetEntity: ScopeRole::class,
        joinTable: 'user_role',
        foreignKey: 'user_id',
        inverseForeignKey: 'role_id',
        sourceFilter: 'filterSpecialRoles'
    )]
    public ?EntityCollection $filteredRoles = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}

#[Table('profiles')]
#[Entity]
#[Repository(ScopeProfileRepository::class)]
class ScopeProfile implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[Column(name: 'profile_id')]
    public ?int $id = null;

    #[Column(name: 'nickname')]
    public ?string $nickname = null;

    #[Column(name: 'created_at')]
    public ?string $created_at = null;

    #[Column(name: 'updated_at')]
    public ?string $updated_at = null;

    #[BelongsToOne(targetEntity: ScopeUser::class, foreignKey: 'id', targetScope: 'activeUsersOnly')]
    public ?ScopeUser $user = null;

    #[BelongsToOne(targetEntity: ScopeUser::class, foreignKey: 'id', sourceFilter: 'onlyAdminUsers')]
    public ?ScopeUser $adminUser = null;

    #[BelongsToOne(targetEntity: ScopeUser::class, foreignKey: 'id', targetScope: 'nonExistentScope')]
    public ?ScopeUser $invalidScopeUser = null;

    #[BelongsToOne(targetEntity: ScopeUser::class, foreignKey: 'id', sourceFilter: 'nonExistentFilter')]
    public ?ScopeUser $invalidFilterUser = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}

#[Table('roles')]
#[Entity]
#[Repository(ScopeRoleRepository::class)]
class ScopeRole implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'role_id')]
    public ?int $role_id = null;

    #[Column(name: 'role_name')]
    public ?string $role_name = null;

    public function getId(): ?int
    {
        return $this->role_id;
    }
}

#[Repository(ScopeProject::class)]
class ScopeProjectRepository extends AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, ScopeProject::class);
    }

    public function filterUrgentTasks(Query $query, EntityInterface|EntityCollection $projects): void
    {
        $query->where('title', '%URGENT%', 'LIKE');
    }

    public function activeProjects(Query $query, EntityInterface|EntityCollection $source): void
    {
        $query->where('name', 'INACTIVE:%', 'NOT LIKE');
    }
}

#[Repository(ScopeTask::class)]
class ScopeTaskRepository extends AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, ScopeTask::class);
    }

    public function activeOnly(Query $query, EntityInterface|EntityCollection $source): void
    {
        $query->where('title', 'DELETED:%', 'NOT LIKE');
    }

    public function titlePrefixedByProjectName(Query $query, EntityInterface|EntityCollection $projects): void
    {
        $project = $projects instanceof EntityInterface ? $projects : $projects->first();
        if (!$project instanceof EntityInterface) {
            return;
        }
        $query->where('title', $project->getRaw('name') . ':%', 'LIKE');
    }

    public function onlySpecialProjects(Query $query, EntityInterface|EntityCollection $tasks): void
    {
        $query->where('name', '%Special%', 'LIKE');
    }
}

#[Repository(ScopeUser::class)]
class ScopeUserRepository extends AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, ScopeUser::class);
    }

    public function filterAdminProfile(Query $query, EntityInterface|EntityCollection $users): void
    {
        $query->where('nickname', 'admin_%', 'LIKE');
    }

    public function activeUsersOnly(Query $query, EntityInterface|EntityCollection $source): void
    {
        $query->where('user_name', 'banned_%', 'NOT LIKE');
    }

    public function filterSpecialRoles(Query $query, EntityInterface|EntityCollection $users): void
    {
        $query->where('role_name', 'SPECIAL_%', 'LIKE');
    }
}

#[Repository(ScopeProfile::class)]
class ScopeProfileRepository extends AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, ScopeProfile::class);
    }

    public function verifiedOnly(Query $query, EntityInterface|EntityCollection $source): void
    {
        $query->where('nickname', 'unverified_%', 'NOT LIKE');
    }

    public function onlyAdminUsers(Query $query, EntityInterface|EntityCollection $profiles): void
    {
        $query->where('user_name', 'admin_%', 'LIKE');
    }
}

#[Repository(ScopeRole::class)]
class ScopeRoleRepository extends AbstractRepository
{
    public function __construct(OrmManager $manager)
    {
        $this->setUpRepository($manager, null, ScopeRole::class);
    }

    public function adminRolesOnly(Query $query, EntityInterface|EntityCollection $source): void
    {
        $query->where('role_name', 'ROLE_ADMIN%', 'LIKE');
    }
}

final class RelationScopeAndFilterTest extends TestCase
{
    private PDO $pdo;
    private OrmManager $manager;
    private ScopeProjectRepository $projectRepo;
    private ScopeTaskRepository $taskRepo;
    private ScopeUserRepository $userRepo;
    private ScopeProfileRepository $profileRepo;
    private ScopeRoleRepository $roleRepo;

    protected function setUp(): void
    {
        $this->pdo = DbConnection::get();
        $this->pdo->exec(SchemaLoader::loadTable('drop_all'));
        $this->pdo->exec(SchemaLoader::loadTable('projects'));
        $this->pdo->exec(SchemaLoader::loadTable('tasks'));
        $this->pdo->exec(SchemaLoader::loadTable('users'));
        $this->pdo->exec(SchemaLoader::loadTable('profiles'));
        $this->pdo->exec(SchemaLoader::loadTable('roles'));
        $this->pdo->exec(SchemaLoader::loadTable('user_role'));

        $container = new TestContainer();
        $container->set(PDO::class, $this->pdo);
        $this->manager = OrmManager::initialize($container);
        $this->projectRepo = new ScopeProjectRepository($this->manager);
        $this->taskRepo = new ScopeTaskRepository($this->manager);
        $this->userRepo = new ScopeUserRepository($this->manager);
        $this->profileRepo = new ScopeProfileRepository($this->manager);
        $this->roleRepo = new ScopeRoleRepository($this->manager);
        $container->set(ScopeProjectRepository::class, $this->projectRepo);
        $container->set(ScopeTaskRepository::class, $this->taskRepo);
        $container->set(ScopeUserRepository::class, $this->userRepo);
        $container->set(ScopeProfileRepository::class, $this->profileRepo);
        $container->set(ScopeRoleRepository::class, $this->roleRepo);
    }

    public function testAttributeBackwardCompatibilityAndProperties(): void
    {
        $hasMany1 = new HasMany(targetEntity: ScopeTask::class, mappedBy: 'project', sourceFilter: 'filterTasks', targetScope: 'active');
        $this->assertSame('filterTasks', $hasMany1->sourceFilter);
        $this->assertSame('filterTasks', $hasMany1->apply);
        $this->assertSame('active', $hasMany1->targetScope);

        $hasMany2 = new HasMany(targetEntity: ScopeTask::class, mappedBy: 'project', apply: 'filterTasks');
        $this->assertSame('filterTasks', $hasMany2->sourceFilter);
        $this->assertSame('filterTasks', $hasMany2->apply);
        $this->assertNull($hasMany2->targetScope);

        $belongsTo = new BelongsTo(targetEntity: ScopeProject::class, foreignKey: 'project_id', sourceFilter: 'filterProjects', targetScope: 'activeScope');
        $this->assertSame('filterProjects', $belongsTo->sourceFilter);
        $this->assertSame('filterProjects', $belongsTo->apply);
        $this->assertSame('activeScope', $belongsTo->targetScope);

        $hasOne = new HasOne(targetEntity: ScopeProfile::class, mappedBy: 'user', sourceFilter: 'filterProfiles', targetScope: 'verifiedOnly');
        $this->assertSame('filterProfiles', $hasOne->sourceFilter);
        $this->assertSame('filterProfiles', $hasOne->apply);
        $this->assertSame('verifiedOnly', $hasOne->targetScope);

        $belongsToOne = new BelongsToOne(targetEntity: ScopeUser::class, foreignKey: 'id', sourceFilter: 'filterUsers', targetScope: 'activeUsersOnly');
        $this->assertSame('filterUsers', $belongsToOne->sourceFilter);
        $this->assertSame('filterUsers', $belongsToOne->apply);
        $this->assertSame('activeUsersOnly', $belongsToOne->targetScope);

        $manyToMany = new ManyToMany(
            targetEntity: ScopeRole::class,
            joinTable: 'user_role',
            foreignKey: 'user_id',
            inverseForeignKey: 'role_id',
            sourceFilter: 'filterRoles',
            targetScope: 'adminRolesOnly'
        );
        $this->assertSame('filterRoles', $manyToMany->sourceFilter);
        $this->assertSame('filterRoles', $manyToMany->apply);
        $this->assertSame('adminRolesOnly', $manyToMany->targetScope);
    }

    public function testHasManyTargetScope(): void
    {
        $project = $this->projectRepo->createEntity(['name' => 'Project Alpha']);
        $this->projectRepo->save($project);

        $task1 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'Normal Task', 'created_at' => '2026-01-01 00:00:00']);
        $task2 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'DELETED: Old Task', 'created_at' => '2026-01-02 00:00:00']);
        $this->taskRepo->save($task1);
        $this->taskRepo->save($task2);

        $this->manager->getEntityCache()->clear();
        $project = $this->projectRepo->findById($project->getId());

        $loaded = $this->projectRepo->load($project, 'activeTasks');
        $this->assertCount(1, $loaded);
        $this->assertSame('Normal Task', $loaded[0]->getRaw('title'));
    }

    public function testHasManyTargetScopeUsesSourceEntity(): void
    {
        $project = $this->projectRepo->createEntity(['name' => 'Alpha']);
        $this->projectRepo->save($project);

        $task1 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'Alpha: matching', 'created_at' => '2026-01-01 00:00:00']);
        $task2 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'Beta: other', 'created_at' => '2026-01-02 00:00:00']);
        $this->taskRepo->save($task1);
        $this->taskRepo->save($task2);

        $this->manager->getEntityCache()->clear();
        $project = $this->projectRepo->findById($project->getId());

        $loaded = $this->projectRepo->load($project, 'prefixedTasks');
        $this->assertCount(1, $loaded);
        $this->assertSame('Alpha: matching', $loaded[0]->getRaw('title'));
    }

    public function testHasManySourceFilter(): void
    {
        $project = $this->projectRepo->createEntity(['name' => 'Project Beta']);
        $this->projectRepo->save($project);

        $task1 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'URGENT: Fix bug', 'created_at' => '2026-01-01 00:00:00']);
        $task2 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'Normal Task', 'created_at' => '2026-01-02 00:00:00']);
        $this->taskRepo->save($task1);
        $this->taskRepo->save($task2);

        $this->manager->getEntityCache()->clear();
        $project = $this->projectRepo->findById($project->getId());

        $loaded = $this->projectRepo->load($project, 'urgentTasks');
        $this->assertCount(1, $loaded);
        $this->assertSame('URGENT: Fix bug', $loaded[0]->getRaw('title'));
    }

    public function testHasManyApplyBackwardCompatibility(): void
    {
        $project = $this->projectRepo->createEntity(['name' => 'Project Gamma']);
        $this->projectRepo->save($project);

        $task1 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'URGENT: Release', 'created_at' => '2026-01-01 00:00:00']);
        $task2 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'Normal Task', 'created_at' => '2026-01-02 00:00:00']);
        $this->taskRepo->save($task1);
        $this->taskRepo->save($task2);

        $this->manager->getEntityCache()->clear();
        $project = $this->projectRepo->findById($project->getId());

        $loaded = $this->projectRepo->load($project, 'legacyUrgentTasks');
        $this->assertCount(1, $loaded);
        $this->assertSame('URGENT: Release', $loaded[0]->getRaw('title'));
    }

    public function testHasManyBothTargetScopeAndSourceFilter(): void
    {
        $project = $this->projectRepo->createEntity(['name' => 'Project Delta']);
        $this->projectRepo->save($project);

        $task1 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'URGENT: Fix server', 'created_at' => '2026-01-01 00:00:00']);
        $task2 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'DELETED: URGENT: Old', 'created_at' => '2026-01-02 00:00:00']);
        $task3 = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'Normal Task', 'created_at' => '2026-01-03 00:00:00']);
        $this->taskRepo->save($task1);
        $this->taskRepo->save($task2);
        $this->taskRepo->save($task3);

        $this->manager->getEntityCache()->clear();
        $project = $this->projectRepo->findById($project->getId());

        $urgent = $this->projectRepo->load($project, 'urgentTasks');
        $active = $this->projectRepo->load($project, 'activeTasks');
        $both = $this->projectRepo->load($project, 'activeUrgentTasks');

        // sourceFilter only: Task1 + Task2 (title contains URGENT)
        $this->assertCount(2, $urgent);
        // targetScope only: Task1 + Task3 (title does not start with DELETED:)
        $this->assertCount(2, $active);
        // both: only Task1
        $this->assertCount(1, $both);
        $this->assertSame('URGENT: Fix server', $both[0]->getRaw('title'));
    }

    public function testHasManyBatchLoad(): void
    {
        $p1 = $this->projectRepo->createEntity(['name' => 'P1']);
        $p2 = $this->projectRepo->createEntity(['name' => 'P2']);
        $this->projectRepo->save($p1);
        $this->projectRepo->save($p2);

        $t1 = $this->taskRepo->createEntity(['project_id' => $p1->getId(), 'title' => 'Normal Task 1', 'created_at' => '2026-01-01 00:00:00']);
        $t2 = $this->taskRepo->createEntity(['project_id' => $p1->getId(), 'title' => 'DELETED: Old 1', 'created_at' => '2026-01-01 00:00:00']);
        $t3 = $this->taskRepo->createEntity(['project_id' => $p2->getId(), 'title' => 'Normal Task 2', 'created_at' => '2026-01-01 00:00:00']);
        $this->taskRepo->save($t1);
        $this->taskRepo->save($t2);
        $this->taskRepo->save($t3);

        $this->manager->getEntityCache()->clear();
        $projects = [
            $this->projectRepo->findById($p1->getId()),
            $this->projectRepo->findById($p2->getId()),
        ];

        $loaded = $this->projectRepo->load($projects, 'activeTasks');
        $this->assertCount(2, $loaded);
        $this->assertCount(1, $projects[0]->getRaw('activeTasks'));
        $this->assertCount(1, $projects[1]->getRaw('activeTasks'));
    }

    public function testHasManyTargetScopeNotFoundThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target scope method "nonExistentScope" not found');

        $project = $this->projectRepo->createEntity(['name' => 'Project Test']);
        $this->projectRepo->save($project);

        $this->projectRepo->load($project, 'invalidScopeTasks');
    }

    public function testHasManySourceFilterNotFoundThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source filter method "nonExistentFilter" not found');

        $project = $this->projectRepo->createEntity(['name' => 'Project Test']);
        $this->projectRepo->save($project);

        $this->projectRepo->load($project, 'invalidFilterTasks');
    }

    public function testBelongsToTargetScope(): void
    {
        $activeProject = $this->projectRepo->createEntity(['name' => 'Active Project']);
        $inactiveProject = $this->projectRepo->createEntity(['name' => 'INACTIVE: Archival Project']);
        $this->projectRepo->save($activeProject);
        $this->projectRepo->save($inactiveProject);

        $task1 = $this->taskRepo->createEntity(['project_id' => $activeProject->getId(), 'title' => 'Task 1']);
        $task2 = $this->taskRepo->createEntity(['project_id' => $inactiveProject->getId(), 'title' => 'Task 2']);
        $this->taskRepo->save($task1);
        $this->taskRepo->save($task2);

        $this->manager->getEntityCache()->clear();
        $task1 = $this->taskRepo->findById($task1->getId());
        $task2 = $this->taskRepo->findById($task2->getId());

        $loaded1 = $this->taskRepo->load($task1, 'activeProject');
        $this->assertCount(1, $loaded1);
        $this->assertSame('Active Project', $task1->getRaw('activeProject')->getRaw('name'));

        $loaded2 = $this->taskRepo->load($task2, 'activeProject');
        $this->assertCount(0, $loaded2);
        $this->assertNull($task2->getRaw('activeProject'));
    }

    public function testBelongsToSourceFilter(): void
    {
        $specialProject = $this->projectRepo->createEntity(['name' => 'Special Project']);
        $regularProject = $this->projectRepo->createEntity(['name' => 'Regular Project']);
        $this->projectRepo->save($specialProject);
        $this->projectRepo->save($regularProject);

        $task1 = $this->taskRepo->createEntity(['project_id' => $specialProject->getId(), 'title' => 'Task 1']);
        $task2 = $this->taskRepo->createEntity(['project_id' => $regularProject->getId(), 'title' => 'Task 2']);
        $this->taskRepo->save($task1);
        $this->taskRepo->save($task2);

        $this->manager->getEntityCache()->clear();
        $task1 = $this->taskRepo->findById($task1->getId());
        $task2 = $this->taskRepo->findById($task2->getId());

        $loaded1 = $this->taskRepo->load($task1, 'specialProject');
        $this->assertCount(1, $loaded1);
        $this->assertSame('Special Project', $task1->getRaw('specialProject')->getRaw('name'));

        $loaded2 = $this->taskRepo->load($task2, 'specialProject');
        $this->assertCount(0, $loaded2);
        $this->assertNull($task2->getRaw('specialProject'));
    }

    public function testBelongsToApplyBackwardCompatibility(): void
    {
        $specialProject = $this->projectRepo->createEntity(['name' => 'Special Project']);
        $this->projectRepo->save($specialProject);

        $task = $this->taskRepo->createEntity(['project_id' => $specialProject->getId(), 'title' => 'Task 1']);
        $this->taskRepo->save($task);

        $this->manager->getEntityCache()->clear();
        $task = $this->taskRepo->findById($task->getId());

        $loaded = $this->taskRepo->load($task, 'legacySpecialProject');
        $this->assertCount(1, $loaded);
        $this->assertSame('Special Project', $task->getRaw('legacySpecialProject')->getRaw('name'));
    }

    public function testBelongsToBothTargetScopeAndSourceFilter(): void
    {
        $special = $this->projectRepo->createEntity(['name' => 'Special Project']);
        $inactiveSpecial = $this->projectRepo->createEntity(['name' => 'INACTIVE: Special Archive']);
        $regular = $this->projectRepo->createEntity(['name' => 'Regular Project']);
        $this->projectRepo->save($special);
        $this->projectRepo->save($inactiveSpecial);
        $this->projectRepo->save($regular);

        $taskSpecial = $this->taskRepo->createEntity(['project_id' => $special->getId(), 'title' => 'T1']);
        $taskInactiveSpecial = $this->taskRepo->createEntity(['project_id' => $inactiveSpecial->getId(), 'title' => 'T2']);
        $taskRegular = $this->taskRepo->createEntity(['project_id' => $regular->getId(), 'title' => 'T3']);
        $this->taskRepo->save($taskSpecial);
        $this->taskRepo->save($taskInactiveSpecial);
        $this->taskRepo->save($taskRegular);

        $this->manager->getEntityCache()->clear();
        $taskSpecial = $this->taskRepo->findById($taskSpecial->getId());
        $taskInactiveSpecial = $this->taskRepo->findById($taskInactiveSpecial->getId());
        $taskRegular = $this->taskRepo->findById($taskRegular->getId());

        $loadedSpecial = $this->taskRepo->load($taskSpecial, 'activeSpecialProject');
        $loadedInactive = $this->taskRepo->load($taskInactiveSpecial, 'activeSpecialProject');
        $loadedRegular = $this->taskRepo->load($taskRegular, 'activeSpecialProject');

        $this->assertCount(1, $loadedSpecial);
        $this->assertSame('Special Project', $taskSpecial->getRaw('activeSpecialProject')->getRaw('name'));
        $this->assertCount(0, $loadedInactive);
        $this->assertNull($taskInactiveSpecial->getRaw('activeSpecialProject'));
        $this->assertCount(0, $loadedRegular);
        $this->assertNull($taskRegular->getRaw('activeSpecialProject'));
    }

    public function testBelongsToBatchLoad(): void
    {
        $activeProject = $this->projectRepo->createEntity(['name' => 'Active Project']);
        $inactiveProject = $this->projectRepo->createEntity(['name' => 'INACTIVE: Archival Project']);
        $this->projectRepo->save($activeProject);
        $this->projectRepo->save($inactiveProject);

        $t1 = $this->taskRepo->createEntity(['project_id' => $activeProject->getId(), 'title' => 'Task 1']);
        $t2 = $this->taskRepo->createEntity(['project_id' => $inactiveProject->getId(), 'title' => 'Task 2']);
        $this->taskRepo->save($t1);
        $this->taskRepo->save($t2);

        $this->manager->getEntityCache()->clear();
        $tasks = [
            $this->taskRepo->findById($t1->getId()),
            $this->taskRepo->findById($t2->getId()),
        ];

        $loaded = $this->taskRepo->load($tasks, 'activeProject');
        $this->assertCount(1, $loaded);
        $this->assertNotNull($tasks[0]->getRaw('activeProject'));
        $this->assertNull($tasks[1]->getRaw('activeProject'));
    }

    public function testBelongsToTargetScopeNotFoundThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target scope method "nonExistentScope" not found');

        $project = $this->projectRepo->createEntity(['name' => 'P1']);
        $this->projectRepo->save($project);
        $task = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'T1']);
        $this->taskRepo->save($task);

        $this->taskRepo->load($task, 'invalidScopeProject');
    }

    public function testBelongsToSourceFilterNotFoundThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source filter method "nonExistentFilter" not found');

        $project = $this->projectRepo->createEntity(['name' => 'P1']);
        $this->projectRepo->save($project);
        $task = $this->taskRepo->createEntity(['project_id' => $project->getId(), 'title' => 'T1']);
        $this->taskRepo->save($task);

        $this->taskRepo->load($task, 'invalidFilterProject');
    }

    public function testHasOneTargetScope(): void
    {
        $user = $this->userRepo->createEntity(['name' => 'alice', 'email' => 'alice@example.com']);
        $this->userRepo->save($user);

        $profile = $this->profileRepo->createEntity(['id' => $user->getId(), 'nickname' => 'unverified_alice']);
        $this->profileRepo->save($profile);

        $this->manager->getEntityCache()->clear();
        $user = $this->userRepo->findById($user->getId());

        $loaded = $this->userRepo->load($user, 'verifiedProfile');
        $this->assertCount(0, $loaded);
        $this->assertNull($user->getRaw('verifiedProfile'));

        // Update profile to verified
        $profile = $this->profileRepo->findById($user->getId());
        $profile->setRaw('nickname', 'verified_alice');
        $this->profileRepo->save($profile);

        $this->manager->getEntityCache()->clear();
        $user = $this->userRepo->findById($user->getId());

        $loaded = $this->userRepo->load($user, 'verifiedProfile');
        $this->assertCount(1, $loaded);
        $this->assertSame('verified_alice', $user->getRaw('verifiedProfile')->getRaw('nickname'));
    }

    public function testHasOneSourceFilter(): void
    {
        $user = $this->userRepo->createEntity(['name' => 'bob', 'email' => 'bob@example.com']);
        $this->userRepo->save($user);

        $profile = $this->profileRepo->createEntity(['id' => $user->getId(), 'nickname' => 'user_bob']);
        $this->profileRepo->save($profile);

        $this->manager->getEntityCache()->clear();
        $user = $this->userRepo->findById($user->getId());

        $loaded = $this->userRepo->load($user, 'adminProfile');
        $this->assertCount(0, $loaded);

        $profile = $this->profileRepo->findById($user->getId());
        $profile->setRaw('nickname', 'admin_bob');
        $this->profileRepo->save($profile);

        $this->manager->getEntityCache()->clear();
        $user = $this->userRepo->findById($user->getId());

        $loaded = $this->userRepo->load($user, 'adminProfile');
        $this->assertCount(1, $loaded);
        $this->assertSame('admin_bob', $user->getRaw('adminProfile')->getRaw('nickname'));
    }

    public function testBelongsToOneTargetScope(): void
    {
        $userBanned = $this->userRepo->createEntity(['name' => 'banned_user', 'email' => 'banned@example.com']);
        $userActive = $this->userRepo->createEntity(['name' => 'alice', 'email' => 'alice@example.com']);
        $this->userRepo->save($userBanned);
        $this->userRepo->save($userActive);

        $profileBanned = $this->profileRepo->createEntity(['id' => $userBanned->getId(), 'nickname' => 'banned_profile']);
        $profileActive = $this->profileRepo->createEntity(['id' => $userActive->getId(), 'nickname' => 'alice_profile']);
        $this->profileRepo->save($profileBanned);
        $this->profileRepo->save($profileActive);

        $this->manager->getEntityCache()->clear();
        $profileBanned = $this->profileRepo->findById($profileBanned->getId());
        $profileActive = $this->profileRepo->findById($profileActive->getId());

        $loadedBanned = $this->profileRepo->load($profileBanned, 'user');
        $this->assertCount(0, $loadedBanned);
        $this->assertNull($profileBanned->getRaw('user'));

        $loadedActive = $this->profileRepo->load($profileActive, 'user');
        $this->assertCount(1, $loadedActive);
        $this->assertSame('alice', $profileActive->getRaw('user')->getRaw('name'));
    }

    public function testBelongsToOneSourceFilter(): void
    {
        $admin = $this->userRepo->createEntity(['name' => 'admin_bob', 'email' => 'admin@example.com']);
        $regular = $this->userRepo->createEntity(['name' => 'bob', 'email' => 'bob@example.com']);
        $this->userRepo->save($admin);
        $this->userRepo->save($regular);

        $adminProfile = $this->profileRepo->createEntity(['id' => $admin->getId(), 'nickname' => 'admin_profile']);
        $regularProfile = $this->profileRepo->createEntity(['id' => $regular->getId(), 'nickname' => 'regular_profile']);
        $this->profileRepo->save($adminProfile);
        $this->profileRepo->save($regularProfile);

        $this->manager->getEntityCache()->clear();
        $adminProfile = $this->profileRepo->findById($adminProfile->getId());
        $regularProfile = $this->profileRepo->findById($regularProfile->getId());

        $loadedAdmin = $this->profileRepo->load($adminProfile, 'adminUser');
        $this->assertCount(1, $loadedAdmin);
        $this->assertSame('admin_bob', $adminProfile->getRaw('adminUser')->getRaw('name'));

        $loadedRegular = $this->profileRepo->load($regularProfile, 'adminUser');
        $this->assertCount(0, $loadedRegular);
        $this->assertNull($regularProfile->getRaw('adminUser'));
    }

    public function testBelongsToOneBatchLoad(): void
    {
        $userBanned = $this->userRepo->createEntity(['name' => 'banned_user', 'email' => 'banned@example.com']);
        $userActive = $this->userRepo->createEntity(['name' => 'alice', 'email' => 'alice@example.com']);
        $this->userRepo->save($userBanned);
        $this->userRepo->save($userActive);

        $profileBanned = $this->profileRepo->createEntity(['id' => $userBanned->getId(), 'nickname' => 'banned_profile']);
        $profileActive = $this->profileRepo->createEntity(['id' => $userActive->getId(), 'nickname' => 'alice_profile']);
        $this->profileRepo->save($profileBanned);
        $this->profileRepo->save($profileActive);

        $this->manager->getEntityCache()->clear();
        $profiles = [
            $this->profileRepo->findById($profileBanned->getId()),
            $this->profileRepo->findById($profileActive->getId()),
        ];

        $loaded = $this->profileRepo->load($profiles, 'user');
        $this->assertCount(1, $loaded);
        $this->assertNull($profiles[0]->getRaw('user'));
        $this->assertNotNull($profiles[1]->getRaw('user'));
        $this->assertSame('alice', $profiles[1]->getRaw('user')->getRaw('name'));
    }

    public function testBelongsToOneTargetScopeNotFoundThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target scope method "nonExistentScope" not found');

        $user = $this->userRepo->createEntity(['name' => 'alice', 'email' => 'alice@example.com']);
        $this->userRepo->save($user);
        $profile = $this->profileRepo->createEntity(['id' => $user->getId(), 'nickname' => 'alice_profile']);
        $this->profileRepo->save($profile);

        $this->profileRepo->load($profile, 'invalidScopeUser');
    }

    public function testBelongsToOneSourceFilterNotFoundThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source filter method "nonExistentFilter" not found');

        $user = $this->userRepo->createEntity(['name' => 'alice', 'email' => 'alice@example.com']);
        $this->userRepo->save($user);
        $profile = $this->profileRepo->createEntity(['id' => $user->getId(), 'nickname' => 'alice_profile']);
        $this->profileRepo->save($profile);

        $this->profileRepo->load($profile, 'invalidFilterUser');
    }

    public function testManyToManyTargetScope(): void
    {
        $user = $this->userRepo->createEntity(['name' => 'charlie', 'email' => 'charlie@example.com']);
        $this->userRepo->save($user);

        $adminRole = $this->roleRepo->createEntity(['role_name' => 'ROLE_ADMIN_SUPER']);
        $userRole = $this->roleRepo->createEntity(['role_name' => 'ROLE_USER']);
        $this->roleRepo->save($adminRole);
        $this->roleRepo->save($userRole);

        // Link in join table
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user->getId()}, {$adminRole->getId()})");
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user->getId()}, {$userRole->getId()})");

        $this->manager->getEntityCache()->clear();
        $user = $this->userRepo->findById($user->getId());

        $loaded = $this->userRepo->load($user, 'adminRoles');
        $this->assertCount(1, $loaded);
        $this->assertSame('ROLE_ADMIN_SUPER', $loaded[0]->getRaw('role_name'));
    }

    public function testManyToManySourceFilter(): void
    {
        $user = $this->userRepo->createEntity(['name' => 'david', 'email' => 'david@example.com']);
        $this->userRepo->save($user);

        $specialRole = $this->roleRepo->createEntity(['role_name' => 'SPECIAL_VIP']);
        $regularRole = $this->roleRepo->createEntity(['role_name' => 'REGULAR_MEMBER']);
        $this->roleRepo->save($specialRole);
        $this->roleRepo->save($regularRole);

        // Link in join table
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user->getId()}, {$specialRole->getId()})");
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user->getId()}, {$regularRole->getId()})");

        $this->manager->getEntityCache()->clear();
        $user = $this->userRepo->findById($user->getId());

        $loaded = $this->userRepo->load($user, 'filteredRoles');
        $this->assertCount(1, $loaded);
        $this->assertSame('SPECIAL_VIP', $loaded[0]->getRaw('role_name'));
    }
}
