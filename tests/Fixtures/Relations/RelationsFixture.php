<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use PDO;
use WScore\DecaORM\Tests\Support\DbConnection;
use WScore\DecaORM\Tests\Support\SchemaLoader;
use Psr\Log\LoggerInterface;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\OrmManager;

class RelationsFixture
{
    public PDO $pdo;
    public TestContainer $container;
    public UserRepository $users;
    public PostRepository $posts;
    public CommentRepository $comments;
    public ProfileRepository $profiles;
    public RoleRepository $roles;
    public OrmManager $manager;

    public static function create(?LoggerInterface $logger = null, int $slowQueryThresholdMs = 100): self
    {
        $fixture = new self();
        $fixture->pdo = DbConnection::get();
        $fixture->container = new TestContainer();
        $fixture->container->set(PDO::class, $fixture->pdo);
        $fixture->manager = OrmManager::initialize($fixture->container);
        if ($logger !== null) {
            $fixture->manager->setLogger($logger);
        }
        $fixture->manager->setSlowQueryThresholdMs($slowQueryThresholdMs);

        $schemaDir = SchemaLoader::getSchemaDir();
        $dropAll = file_get_contents($schemaDir . '/drop_all.sql');
        if ($dropAll !== false) {
            $fixture->pdo->exec($dropAll);
        }
        foreach (self::schemaFileNames() as $name) {
            $sql = file_get_contents($schemaDir . '/' . $name);
            if ($sql !== false) {
                $fixture->pdo->exec($sql);
            }
        }

        EntityCache::clear();

        $fixture->users = new UserRepository($fixture->manager);
        $fixture->posts = new PostRepository($fixture->manager);
        $fixture->comments = new CommentRepository($fixture->manager);
        $fixture->profiles = new ProfileRepository($fixture->manager);
        $fixture->roles = new RoleRepository($fixture->manager);

        $fixture->container->set(UserRepository::class, $fixture->users);
        $fixture->container->set(PostRepository::class, $fixture->posts);
        $fixture->container->set(CommentRepository::class, $fixture->comments);
        $fixture->container->set(ProfileRepository::class, $fixture->profiles);
        $fixture->container->set(RoleRepository::class, $fixture->roles);

        return $fixture;
    }

    /**
     * @return list<string>
     */
    private static function schemaFileNames(): array
    {
        return [
            'users.sql',
            'posts.sql',
            'comments.sql',
            'profiles.sql',
            'roles.sql',
            'user_role.sql',
        ];
    }
}
