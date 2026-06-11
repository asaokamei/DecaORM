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
    public static function getApply(HasMany|HasOne|ManyToMany $parentRelation, ?RepositoryInterface $sourceRepository): ?array
    {
        $apply = null;
        if ($parentRelation->apply !== null) {
            if ($sourceRepository === null) {
                throw new RuntimeException(
                    'Source repository is required when using apply. ' .
                    'Please pass the source repository to LoadHasMany/LoadHasOne::load()'
                );
            }

            if (!method_exists($sourceRepository, $parentRelation->apply)) {
                throw new RuntimeException(
                    'Apply method "' . $parentRelation->apply . '" not found in repository: ' . $sourceRepository::class
                );
            }
            $apply = [$sourceRepository, $parentRelation->apply];
        }
        return $apply;
    }

    /**
     * Normalizes apply method to a callable that accepts:
     *   (Query $query, EntityInterface|EntityCollection $owners, object $inverseRelation, RepositoryInterface $targetRepo, RepositoryInterface $ownerRepo): void
     */
    public static function wrapApply(?array $apply): ?callable
    {
        if ($apply === null) {
            return null;
        }

        return function (
            Query $query,
            EntityInterface|EntityCollection $owners,
            object $inverseRelation,
            RepositoryInterface $targetRepo,
            RepositoryInterface $ownerRepo
        ) use ($apply): void {
            // Support both signatures:
            // - (Query $query, EntityInterface|EntityCollection $owners): void
            // - (Query $query, EntityInterface|EntityCollection $owners, object $inverseRelation, RepositoryInterface $targetRepo, RepositoryInterface $ownerRepo): void
            $method = $apply[1] ?? null;
            $argc = is_string($method) && method_exists($apply[0], $method)
                ? (new \ReflectionMethod($apply[0], $method))->getNumberOfParameters()
                : 2;

            if ($argc <= 2) {
                ($apply)($query, $owners);
                return;
            }
            ($apply)($query, $owners, $inverseRelation, $targetRepo, $ownerRepo);
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
