<?php

namespace WScore\DecaORM;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class RepositoryManager
{
    private static ?ContainerInterface $container = null;

    /**
     * Scoped containers stack (per request/job/tenant).
     *
     * @var ContainerInterface[]
     */
    private static array $containerStack = [];

    /**
     * @param ContainerInterface $container
     */
    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * Enters a scoped container.
     *
     * Typical usage: middleware/job wrapper sets tenant container here.
     */
    public static function enterScope(ContainerInterface $container): void
    {
        self::$containerStack[] = $container;
    }

    /**
     * Leaves the current scope.
     *
     * Always pair with enterScope() (prefer runWithContainer()).
     */
    public static function leaveScope(): void
    {
        array_pop(self::$containerStack);
    }

    /**
     * Runs callback within a scoped container and always restores the scope.
     *
     * @template TReturn
     * @param ContainerInterface $container
     * @param callable():TReturn $callback
     * @return TReturn
     */
    public static function runWithContainer(ContainerInterface $container, callable $callback)
    {
        self::enterScope($container);
        try {
            return $callback();
        } finally {
            self::leaveScope();
        }
    }

    /**
     * @return ContainerInterface|null
     */
    private static function getCurrentContainer(): ?ContainerInterface
    {
        if (!empty(self::$containerStack)) {
            return self::$containerStack[count(self::$containerStack) - 1];
        }
        return self::$container;
    }

    /**
     * @template T of RepositoryInterface
     * @param class-string<T> $repositoryClass
     * @return T
     */
    public static function get(string $repositoryClass): RepositoryInterface
    {
        $container = self::getCurrentContainer();

        if ($container === null) {
            throw new \RuntimeException('RepositoryManager container is not set.');
        }
        try {
            $repo = $container->get($repositoryClass);
        } catch (NotFoundExceptionInterface $e) {
            throw new \RuntimeException("Repository for {$repositoryClass} not found in container.", 0, $e);
        }

        if (!$repo instanceof RepositoryInterface) {
            throw new \RuntimeException("Container entry {$repositoryClass} is not a RepositoryInterface.");
        }

        return $repo;
    }
}
