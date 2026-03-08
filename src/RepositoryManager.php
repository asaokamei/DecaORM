<?php

namespace WScore\DecaORM;

use PDO;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

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

    private function __construct(private ContainerInterface $container) {}

    public static function initialize(ContainerInterface $container): static
    {
        self::$_self = new static($container);
        return self::$_self;
    }

    /**
     * @template T of RepositoryInterface
     * @param class-string<T> $class
     * @return T
     * */
    public static function getRepository(string $class): ?RepositoryInterface
    {
        $repo = self::$_self->get($class);
        if (!$repo instanceof RepositoryInterface) {
            throw new RuntimeException("Container entry {$class} is not a RepositoryInterface.");
        }
        return $repo;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::$_self->getPDO();
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

    public function getDateTimeImmutable(): DateTimeImmutable
    {
        if ($this->now === null) {
            $this->now = new \DateTimeImmutable();
        }
        return $this->now;
    }
}
