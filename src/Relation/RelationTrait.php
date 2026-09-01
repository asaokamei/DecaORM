<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\EntityCollection;
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
        return self::getSourceFilter($relation, $sourceRepository);
    }

    /**
     * @param HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation
     */
    public static function resolveTargetScope(
        HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation,
        RepositoryInterface $targetRepository
    ): ?callable {
        return self::getTargetScope($relation, $targetRepository);
    }

    /**
     * @param HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation
     * @return callable|null callable(Query, EntityInterface|EntityCollection): void
     */
    public static function getSourceFilter(
        HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation,
        ?RepositoryInterface $sourceRepository
    ): ?callable {
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
     */
    public static function getApply(
        HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation,
        ?RepositoryInterface $sourceRepository
    ): ?callable {
        return self::getSourceFilter($relation, $sourceRepository);
    }

    /**
     * @param HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation
     * @return callable|null callable(Query, EntityInterface|EntityCollection): void
     */
    public static function getTargetScope(
        HasMany|HasOne|ManyToMany|BelongsTo|BelongsToOne $relation,
        RepositoryInterface $targetRepository
    ): ?callable {
        return self::resolveRepoMethod(
            $relation->targetScope ?? null,
            $targetRepository,
            'Target scope method',
            null
        );
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
     * @return callable|null
     */
    private static function resolveRepoMethod(
        ?string $name,
        ?RepositoryInterface $repository,
        string $missingMethodLabel,
        ?string $missingRepoMessage
    ): ?callable {
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
}
