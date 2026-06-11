<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Tests\Fixtures\ArrayLogger;
use WScore\DecaORM\Tests\Fixtures\Relations\RelationsFixture;
use WScore\DecaORM\Tests\Fixtures\Relations\Role;
use WScore\DecaORM\Tests\Fixtures\Relations\RoleRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;

class ManyToManyTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private RoleRepository $roleRepo;
    private OrmManager $manager;

    protected function setUp(): void
    {
        $fixture = RelationsFixture::create();
        $this->pdo = $fixture->pdo;
        $this->userRepo = $fixture->users;
        $this->roleRepo = $fixture->roles;
        $this->manager = $fixture->manager;
    }

    public function testFillRolesForUser(): void
    {
        $user = $this->userRepo->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $this->userRepo->save($user);
        $admin = $this->roleRepo->create(['name' => 'admin']);
        $this->roleRepo->save($admin);
        $editor = $this->roleRepo->create(['name' => 'editor']);
        $this->roleRepo->save($editor);

        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user->getId()}, {$admin->getId()})");
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user->getId()}, {$editor->getId()})");

        $this->manager->getEntityCache()->clear();
        $user = $this->userRepo->findById($user->getId());
        $roles = $this->userRepo->load($user, 'roles');

        $this->assertCount(2, $roles);
        $userRoles = $user->getRaw('roles');
        $this->assertInstanceOf(EntityCollection::class, $userRoles);
        $this->assertCount(2, $userRoles);
        $this->assertContains($admin->getId(), $roles->getIds());
        $this->assertContains($editor->getId(), $roles->getIds());
    }

    public function testFillUsersForRole(): void
    {
        $role = $this->roleRepo->create(['name' => 'viewer']);
        $this->roleRepo->save($role);
        $user1 = $this->userRepo->create(['name' => 'A', 'email' => 'a@example.com']);
        $this->userRepo->save($user1);
        $user2 = $this->userRepo->create(['name' => 'B', 'email' => 'b@example.com']);
        $this->userRepo->save($user2);

        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user1->getId()}, {$role->getId()})");
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user2->getId()}, {$role->getId()})");

        $this->manager->getEntityCache()->clear();
        $role = $this->roleRepo->findById($role->getId());
        $users = $this->roleRepo->load($role, 'users');

        $this->assertCount(2, $users);
        $this->assertContains($user1->getId(), $users->getIds());
        $this->assertContains($user2->getId(), $users->getIds());
    }

    public function testBatchLoadRolesForUsers(): void
    {
        $user1 = $this->userRepo->create(['name' => 'User One', 'email' => 'u1@example.com']);
        $this->userRepo->save($user1);
        $user2 = $this->userRepo->create(['name' => 'User Two', 'email' => 'u2@example.com']);
        $this->userRepo->save($user2);
        $role1 = $this->roleRepo->create(['name' => 'admin']);
        $this->roleRepo->save($role1);
        $role2 = $this->roleRepo->create(['name' => 'editor']);
        $this->roleRepo->save($role2);
        $role3 = $this->roleRepo->create(['name' => 'viewer']);
        $this->roleRepo->save($role3);

        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user1->getId()}, {$role1->getId()})");
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user1->getId()}, {$role2->getId()})");
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user2->getId()}, {$role2->getId()})");
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user2->getId()}, {$role3->getId()})");

        $this->manager->getEntityCache()->clear();
        $user1 = $this->userRepo->findById($user1->getId());
        $user2 = $this->userRepo->findById($user2->getId());

        $roles = $this->userRepo->load([$user1, $user2], 'roles');
        $this->assertGreaterThanOrEqual(3, count($roles));
        $this->assertCount(2, $user1->getRaw('roles'));
        $this->assertCount(2, $user2->getRaw('roles'));
    }

    public function testManyToManyApplyFiltersTargetQuery(): void
    {
        $user = $this->userRepo->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->userRepo->save($user);

        $admin = $this->roleRepo->create(['name' => 'admin']);
        $this->roleRepo->save($admin);
        $editor = $this->roleRepo->create(['name' => 'editor']);
        $this->roleRepo->save($editor);

        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user->getId()}, {$admin->getId()})");
        $this->pdo->exec("INSERT INTO user_role (user_id, role_id) VALUES ({$user->getId()}, {$editor->getId()})");

        $this->manager->getEntityCache()->clear();
        $this->userRepo->setRoleNamePrefixFilter('adm');

        $reloadedUser = $this->userRepo->findById($user->getId());
        $roles = $this->userRepo->load($reloadedUser, 'roles');

        $this->assertCount(1, $roles);
        $this->assertSame([$admin->getId()], $roles->getIds());

        $this->userRepo->setRoleNamePrefixFilter(null);
    }

    public function testSyncAddRoles(): void
    {
        $user = $this->userRepo->create(['name' => 'John', 'email' => 'john@example.com']);
        $this->userRepo->save($user);
        $role1 = $this->roleRepo->create(['name' => 'admin']);
        $this->roleRepo->save($role1);
        $role2 = $this->roleRepo->create(['name' => 'editor']);
        $this->roleRepo->save($role2);

        $user->setRoles(new EntityCollection([$role1, $role2]));
        $this->userRepo->syncManyToMany($user, 'roles');

        $stmt = $this->pdo->prepare("SELECT role_id FROM user_role WHERE user_id = ?");
        $stmt->execute([$user->getId()]);
        $roleIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $this->assertCount(2, $roleIds);
        $this->assertContains($role1->getId(), $roleIds);
        $this->assertContains($role2->getId(), $roleIds);
    }

    public function testSyncRequiresEntityId(): void
    {
        $user = $this->userRepo->createEntity([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $role = $this->roleRepo->create(['name' => 'admin']);
        $this->roleRepo->save($role);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entity must have an ID to sync relations');

        $user->setRaw('roles', new EntityCollection([$role]));
        $this->userRepo->syncManyToMany($user, 'roles');
    }

    public function testSyncRequiresManyToManyRelation(): void
    {
        $user = $this->userRepo->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->userRepo->save($user);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Relation 'nonexistent' is not a ManyToMany relationship");

        $this->userRepo->syncManyToMany($user, 'nonexistent');
    }

    public function testManyToManyQueriesAreLogged(): void
    {
        $logger = new ArrayLogger();
        $fixture = RelationsFixture::create($logger, 0);
        $userRepo = $fixture->users;
        $roleRepo = $fixture->roles;

        $user = $userRepo->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $userRepo->save($user);
        $role = $roleRepo->create(['name' => 'admin']);
        $roleRepo->save($role);

        $user->setRoles(new EntityCollection([$role]));
        $userRepo->syncManyToMany($user, 'roles');

        $fixture->manager->getEntityCache()->clear();
        $user = $userRepo->findById($user->getId());
        $userRepo->load($user, 'roles');

        $messages = array_column($logger->records, 'message');
        $sqlList = array_map(
            static fn(array $record): string => $record['context']['sql'] ?? '',
            $logger->records
        );

        $this->assertContains('SQL executed.', $messages);
        $this->assertTrue(
            in_array(
                'SELECT role_id 
                FROM user_role 
                WHERE user_id = :entity_id',
                $sqlList,
                true
            )
        );
    }
}
