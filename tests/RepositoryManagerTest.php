<?php

namespace WScore\DecaORM\Tests;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WScore\DecaORM\AbstractRepository;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\RepositoryManager;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;

class RepositoryManagerTest extends TestCase
{
    public function testGetRepositoryRequiresInitialization(): void
    {
        $previous = $this->replaceRepositoryManager(null);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(
                'RepositoryManager is not initialized. Call RepositoryManager::initialize() first.'
            );

            RepositoryManager::getRepository(UserRepository::class);
        } finally {
            $this->replaceRepositoryManager($previous);
        }
    }

    public function testTransactionRequiresInitialization(): void
    {
        $previous = $this->replaceRepositoryManager(null);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(
                'RepositoryManager is not initialized. Call RepositoryManager::initialize() first.'
            );

            RepositoryManager::transaction(static fn() => true);
        } finally {
            $this->replaceRepositoryManager($previous);
        }
    }

    public function testExecuteRequiresRepositoryManager(): void
    {
        $repo = $this->createRepositoryWithoutManager();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Repository manager is not set. Call setUpRepository() in the repository constructor.'
        );

        $repo->execute('SELECT 1', []);
    }

    public function testNestedRepositoryLookupRequiresRepositoryManager(): void
    {
        $repo = $this->createRepositoryWithoutManager();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Repository manager is not set. Call setUpRepository() in the repository constructor.'
        );

        $repo->getRepository(User::class);
    }

    private function createRepositoryWithoutManager(): AbstractRepository
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new class($pdo) extends AbstractRepository {
            public function __construct(PDO $pdo)
            {
                $this->db = $pdo;
                $this->hydrator = new AttributeHydrator(User::class);
                $this->now = new DateTimeImmutable();
            }
        };
    }

    private function replaceRepositoryManager(?RepositoryManager $manager): ?RepositoryManager
    {
        $property = new \ReflectionProperty(RepositoryManager::class, '_self');
        $previous = $property->getValue();
        $property->setValue(null, $manager);

        return $previous;
    }
}
