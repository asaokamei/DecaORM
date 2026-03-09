<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use PDO;
use Psr\Log\LoggerInterface;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\RepositoryManager;

class RelationsFixture
{
    public PDO $pdo;
    public TestContainer $container;
    public UserRepository $users;
    public PostRepository $posts;
    public CommentRepository $comments;
    public ProfileRepository $profiles;
    public RoleRepository $roles;
    private RepositoryManager $manager;

    public static function create(?LoggerInterface $logger = null, int $slowQueryThresholdMs = 100): self
    {
        $fixture = new self();
        $fixture->pdo = new PDO('sqlite::memory:');
        $fixture->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $fixture->container = new TestContainer();
        $fixture->container->set(PDO::class, $fixture->pdo);
        $fixture->manager = RepositoryManager::initialize($fixture->container);
        if ($logger !== null) {
            $fixture->manager->setLogger($logger);
        }
        $fixture->manager->setSlowQueryThresholdMs($slowQueryThresholdMs);

        foreach (self::schemaFiles() as $file) {
            $sql = file_get_contents($file);
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
    private static function schemaFiles(): array
    {
        $base = __DIR__ . '/Sql';

        return [
            $base . '/users.sql',
            $base . '/posts.sql',
            $base . '/comments.sql',
            $base . '/profiles.sql',
            $base . '/roles.sql',
            $base . '/user_role.sql',
        ];
    }
}
