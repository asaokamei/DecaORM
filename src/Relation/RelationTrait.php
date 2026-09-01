<?php

namespace WScore\DecaORM\Relation;

use ReflectionMethod;
use RuntimeException;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Sql\Query;
use WScore\DecaORM\OrmManager;

/**
 * Relation loading helpers not covered by {@see \WScore\DecaORM\EntityCollection}.
 */
trait RelationTrait
{
    /**
     * @param HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation
     */
    public static function resolveSourceFilter(
        HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation,
        ?RepositoryInterface $sourceRepository
    ): ?callable {
        return self::wrapSourceFilter(self::getSourceFilter($relation, $sourceRepository));
    }

    /**
     * @param HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation
     */
    public static function resolveTargetScope(
        HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation,
        RepositoryInterface $targetRepository
    ): ?callable {
        return self::wrapTargetScope(self::getTargetScope($relation, $targetRepository));
    }

    /**
     * @param HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation
     * @return array{0: RepositoryInterface, 1: string}|null
     */
    public static function getSourceFilter(
        HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation,
        ?RepositoryInterface $sourceRepository
    ): ?array {
        $filter = $relation->sourceFilter ?? $relation->apply ?? null;
        return self::resolveRepoMethod(
            $filter,
            $sourceRepository,
            'Source filter method',
            'Source repository is required when using sourceFilter/apply. ' .
            'Please pass the source repository to load().'
        );
    }

    /**
     * @param HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation
     * @return array{0: RepositoryInterface, 1: string}|null
     */
    public static function getApply(
        HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation,
        ?RepositoryInterface $sourceRepository
    ): ?array {
        return self::getSourceFilter($relation, $sourceRepository);
    }

    /**
     * @param HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation
     * @return array{0: RepositoryInterface, 1: string}|null
     */
    public static function getTargetScope(
        HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation,
        RepositoryInterface $targetRepository
    ): ?array {
        return self::resolveRepoMethod(
            $relation->targetScope ?? null,
            $targetRepository,
            'Target scope method',
            null
        );
    }

    /**
     * Normalizes sourceFilter/apply method to a callable that accepts:
     *   (Query $query, EntityInterface|EntityCollection $owners, object $relation, RepositoryInterface $targetRepo, ?RepositoryInterface $ownerRepo): void
     */
    public static function wrapSourceFilter(?array $filter): ?callable
    {
        return self::wrapQueryHook($filter, queryOnly: false);
    }

    /**
     * Normalizes apply method to a callable (alias for wrapSourceFilter).
     */
    public static function wrapApply(?array $apply): ?callable
    {
        return self::wrapSourceFilter($apply);
    }

    /**
     * Normalizes targetScope method to a callable that accepts:
     *   (Query $query, EntityInterface|EntityCollection $owners, object $relation, RepositoryInterface $targetRepo, ?RepositoryInterface $ownerRepo): void
     */
    public static function wrapTargetScope(?array $scope): ?callable
    {
        return self::wrapQueryHook($scope, queryOnly: true);
    }

    /**
     * Parent repository is required for morph inverse loads; when absent, resolve from parent entity(ies).
     */
    protected static function resolveParentRepositoryForInverse(
        EntityInterface|EntityCollection $parents,
        ?RepositoryInterface $sourceRepository
    ): RepositoryInterface {
        if ($sourceRepository !== null) {
            return $sourceRepository;
        }
        if ($parents instanceof EntityInterface) {
            return OrmManager::getRepository($parents::getRepositoryClass());
        }
        $first = $parents->first();
        if (!$first instanceof EntityInterface) {
            throw new RuntimeException('Cannot resolve parent repository for inverse HasMany/HasOne load');
        }
        return OrmManager::getRepository($first::getRepositoryClass());
    }

    /**
     * @return array{0: RepositoryInterface, 1: string}|null
     */
    private static function resolveRepoMethod(
        ?string $name,
        ?RepositoryInterface $repository,
        string $missingMethodLabel,
        ?string $missingRepoMessage
    ): ?array {
        if ($name === null || $name === '') {
            return null;
        }
        if ($repository === null) {
            throw new RuntimeException(
                $missingRepoMessage ?? ($missingMethodLabel . ' requires a repository.')
            );
        }
        if (!method_exists($repository, $name)) {
            throw new RuntimeException(
                $missingMethodLabel . ' "' . $name . '" not found in repository: ' . $repository::class
            );
        }
        return [$repository, $name];
    }

    /**
     * @param array{0: object, 1: string}|null $method
     */
    private static function wrapQueryHook(?array $method, bool $queryOnly): ?callable
    {
        if ($method === null) {
            return null;
        }

        return function (
            Query $query,
            EntityInterface|EntityCollection $owners,
            object $relation,
            RepositoryInterface $targetRepo,
            ?RepositoryInterface $ownerRepo = null
        ) use ($method, $queryOnly): void {
            $name = $method[1] ?? null;
            $argc = is_string($name) && method_exists($method[0], $name)
                ? (new ReflectionMethod($method[0], $name))->getNumberOfParameters()
                : ($queryOnly ? 1 : 2);

            if ($queryOnly && $argc <= 1) {
                ($method)($query);
                return;
            }
            if ($argc <= 2) {
                ($method)($query, $owners);
                return;
            }
            ($method)($query, $owners, $relation, $targetRepo, $ownerRepo);
        };
    }
}
