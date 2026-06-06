<?php

namespace WScore\DecaORM;

use PDO;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use DateTimeImmutable;
use RuntimeException;
use Throwable;
use WScore\DecaORM\Contracts\SqlParamMaskerInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

class OrmManager
{
    private static ?OrmManager $_self = null;

    private ?PDO $pdo = null;
    private ?DateTimeImmutable $now = null;
    private LoggerInterface $logger;
    private ?SqlExecutor $sqlExecutor = null;
    private int $slowQueryThresholdMs = 100;
    private ?SqlParamMaskerInterface $sqlParamMasker = null;

    private EntityCache $entityCache;

    private DirtyTracker $dirtyTracker;

    private function __construct(private ContainerInterface $container)
    {
        $this->logger = new NullLogger();
        $this->entityCache = new EntityCache();
        $this->dirtyTracker = new DirtyTracker();
    }

    public function getEntityCache(): EntityCache
    {
        return $this->entityCache;
    }

    public function getDirtyTracker(): DirtyTracker
    {
        return $this->dirtyTracker;
    }

    /**
     * Returns the singleton instance after {@see initialize()} (for app code that needs cache/tracker without a repository).
     */
    public static function getInstance(): self
    {
        return self::instance();
    }

    public static function initialize(ContainerInterface $container): static
    {
        self::$_self = new static($container);
        return self::$_self;
    }

    public function setLogger(?LoggerInterface $logger): static
    {
        $this->logger = $logger ?? new NullLogger();
        $this->sqlExecutor = null;
        return $this;
    }

    public function setSlowQueryThresholdMs(int $slowQueryThresholdMs): static
    {
        if ($slowQueryThresholdMs < 0) {
            throw new RuntimeException('Slow query threshold must be 0 or greater.');
        }
        $this->slowQueryThresholdMs = $slowQueryThresholdMs;
        $this->sqlExecutor = null;
        return $this;
    }

    public function setSqlParamMasker(?SqlParamMaskerInterface $sqlParamMasker): static
    {
        $this->sqlParamMasker = $sqlParamMasker;
        $this->sqlExecutor = null;
        return $this;
    }

    /**
     * @template T of RepositoryInterface
     * @param class-string<T> $class
     * @return T
     * */
    public static function getRepository(string $class): ?RepositoryInterface
    {
        $repo = self::instance()->get($class);
        if (!$repo instanceof RepositoryInterface) {
            throw new RuntimeException("Container entry {$class} is not a RepositoryInterface.");
        }
        return $repo;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::instance()->getPDO();
        try {
            $pdo->beginTransaction();
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw new RuntimeException('Failed execute a database transaction.', 0, $e);
        }
    }

    private static function instance(): static
    {
        if (self::$_self === null) {
            throw new RuntimeException('OrmManager is not initialized. Call OrmManager::initialize() first.');
        }

        return self::$_self;
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @return T|mixed
     */
    public function get(string $class): mixed
    {
        try {
            $repo = $this->container->get($class);
        } catch (NotFoundExceptionInterface $e) {
            throw new RuntimeException("Could not found {$class} in container.", 0, $e);
        } catch (ContainerExceptionInterface $e) {
            throw new RuntimeException("Failed to get {$class} from container.", 0, $e);
        }

        return $repo;
    }

    public function getPDO(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->container->get(PDO::class);
        }
        return $this->pdo;
    }

    public function getSqlExecutor(): SqlExecutor
    {
        if ($this->sqlExecutor === null) {
            $this->sqlExecutor = new SqlExecutor(
                new SqlLogger($this->logger, $this->slowQueryThresholdMs, $this->sqlParamMasker)
            );
        }

        return $this->sqlExecutor;
    }

    public function getDateTimeImmutable(): DateTimeImmutable
    {
        if ($this->now === null) {
            $this->now = new \DateTimeImmutable();
        }
        return $this->now;
    }
}
