<?php

namespace WScore\DecaORM;

use Psr\Container\ContainerInterface;

class RepositoryManager
{
    private static ?ContainerInterface $container = null;

    /**
     * @param ContainerInterface $container
     */
    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * @template T of RepositoryInterface
     * @param class-string<T> $repositoryClass
     * @return T
     */
    public static function get(string $repositoryClass): RepositoryInterface
    {
        if (self::$container && self::$container->has($repositoryClass)) {
            return self::$container->get($repositoryClass);
        }
        throw new \RuntimeException("Repository for {$repositoryClass} not found in container.");
    }
}
