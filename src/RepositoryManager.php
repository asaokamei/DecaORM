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
use WScore\DecaORM\Contracts\RepositoryInterface;

class RepositoryManager
{
    private static ?RepositoryManager $_self = null;

    /**
     * Scoped containers stack (per request/job/tenant).
     *
     * @var ContainerInterface[]
     */
    private array $containerStack = [];

    private ?PDO $pdo = null;
    private ?DateTimeImmutable $now = null;
    private LoggerInterface $logger;
    private ?SqlExecutor $sqlExecutor = null;
    private int $slowQueryThresholdMs = 100;

    private function __construct(private ContainerInterface $container)
    {
        $this->logger = new NullLogger();
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
            throw new RuntimeException('RepositoryManager is not initialized. Call RepositoryManager::initialize() first.');
        }

        return self::$_self;
    }

    /**
     * Enters a scoped container.
     *
     * Typical usage: middleware/job wrapper sets a tenant container here.
     */
    public function enterScope(ContainerInterface $container): void
    {
        $this->containerStack[] = $container;
        $this->pdo = null;
    }

    /**
     * Leaves the current scope.
     *
     * Always pair with enterScope() (prefer runWithContainer()).
     */
    public function leaveScope(): void
    {
        array_pop($this->containerStack);
        $this->pdo = null;
    }

    /**
     * Runs callback within a scoped container and always restores the scope.
     *
     * @template TReturn
     * @param ContainerInterface $container
     * @param callable():TReturn $callback
     * @return TReturn
     */
    public function runWithContainer(ContainerInterface $container, callable $callback)
    {
        $this->enterScope($container);
        try {
            return $callback();
        } finally {
            $this->leaveScope();
        }
    }

    /**
     * @return ContainerInterface|null
     */
    private function getCurrentContainer(): ?ContainerInterface
    {
        if (!empty($this->containerStack)) {
            return $this->containerStack[count($this->containerStack) - 1];
        }
        return $this->container;
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @return T|mixed
     */
    public function get(string $class): mixed
    {
        $container = $this->getCurrentContainer();

        if ($container === null) {
            throw new RuntimeException('RepositoryManager container is not set.');
        }
        try {
            $repo = $container->get($class);
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
            $this->pdo = $this->getCurrentContainer()->get(PDO::class);
        }
        return $this->pdo;
    }

    public function getSqlExecutor(): SqlExecutor
    {
        if ($this->sqlExecutor === null) {
            $this->sqlExecutor = new SqlExecutor(
                new SqlLogger($this->logger, $this->slowQueryThresholdMs)
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
