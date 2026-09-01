<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
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
     * @param HasMany|HasOne|ManyToMany $parentRelation
     * @param RepositoryInterface|null $sourceRepository
     * @return array|null
     */
    public static function getSourceFilter(HasMany|HasOne|ManyToMany $parentRelation, ?RepositoryInterface $sourceRepository): ?array
    {
        $filter = $parentRelation->sourceFilter ?? $parentRelation->apply ?? null;
        if ($filter === null || $filter === '') {
            return null;
        }
        if ($sourceRepository === null) {
            throw new RuntimeException(
                'Source repository is required when using sourceFilter/apply. ' .
                'Please pass the source repository to LoadHasMany/LoadHasOne::load()'
            );
        }

        if (!method_exists($sourceRepository, $filter)) {
            throw new RuntimeException(
                'Source filter method "' . $filter . '" not found in repository: ' . $sourceRepository::class
            );
        }
        return [$sourceRepository, $filter];
    }

    /**
     * @param HasMany|HasOne|ManyToMany $parentRelation
     * @param RepositoryInterface|null $sourceRepository
     * @return array|null
     */
    public static function getApply(HasMany|HasOne|ManyToMany $parentRelation, ?RepositoryInterface $sourceRepository): ?array
    {
        return self::getSourceFilter($parentRelation, $sourceRepository);
    }

    /**
     * @param HasMany|HasOne|ManyToMany $parentRelation
     * @param RepositoryInterface $targetRepository
     * @return array|null
     */
    public static function getTargetScope(HasMany|HasOne|ManyToMany $parentRelation, RepositoryInterface $targetRepository): ?array
    {
        $scope = $parentRelation->targetScope ?? null;
        if ($scope === null || $scope === '') {
            return null;
        }

        if (!method_exists($targetRepository, $scope)) {
            throw new RuntimeException(
                'Target scope method "' . $scope . '" not found in repository: ' . $targetRepository::class
            );
        }
        return [$targetRepository, $scope];
    }

    /**
     * Normalizes sourceFilter/apply method to a callable that accepts:
     *   (Query $query, EntityInterface|EntityCollection $owners, object $inverseRelation, RepositoryInterface $targetRepo, RepositoryInterface $ownerRepo): void
     */
    public static function wrapSourceFilter(?array $filter): ?callable
    {
        if ($filter === null) {
            return null;
        }

        return function (
            Query $query,
            EntityInterface|EntityCollection $owners,
            object $inverseRelation,
            RepositoryInterface $targetRepo,
            RepositoryInterface $ownerRepo
        ) use ($filter): void {
            // Support both signatures:
            // - (Query $query, EntityInterface|EntityCollection $owners): void
            // - (Query $query, EntityInterface|EntityCollection $owners, object $inverseRelation, RepositoryInterface $targetRepo, RepositoryInterface $ownerRepo): void
            $method = $filter[1] ?? null;
            $argc = is_string($method) && method_exists($filter[0], $method)
                ? (new \ReflectionMethod($filter[0], $method))->getNumberOfParameters()
                : 2;

            if ($argc <= 2) {
                ($filter)($query, $owners);
                return;
            }
            ($filter)($query, $owners, $inverseRelation, $targetRepo, $ownerRepo);
        };
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
     *   (Query $query, EntityInterface|EntityCollection $owners, object $inverseRelation, RepositoryInterface $targetRepo, ?RepositoryInterface $ownerRepo): void
     */
    public static function wrapTargetScope(?array $scope): ?callable
    {
        if ($scope === null) {
            return null;
        }

        return function (
            Query $query,
            EntityInterface|EntityCollection $owners,
            object $inverseRelation,
            RepositoryInterface $targetRepo,
            ?RepositoryInterface $ownerRepo = null
        ) use ($scope): void {
            $method = $scope[1] ?? null;
            $argc = is_string($method) && method_exists($scope[0], $method)
                ? (new \ReflectionMethod($scope[0], $method))->getNumberOfParameters()
                : 1;

            if ($argc <= 1) {
                ($scope)($query);
                return;
            }
            if ($argc === 2) {
                ($scope)($query, $owners);
                return;
            }
            ($scope)($query, $owners, $inverseRelation, $targetRepo, $ownerRepo);
        };
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
}
