<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
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
     * @param HasMany|HasOne $parentRelation
     * @param RepositoryInterface|null $sourceRepository
     * @return array|null
     */
    public static function getLoader(HasMany|HasOne $parentRelation, ?RepositoryInterface $sourceRepository): ?array
    {
        $loader = null;
        if ($parentRelation->loader !== null) {
            if ($sourceRepository === null) {
                throw new RuntimeException(
                    'Source repository is required when using loader. ' .
                    'Please pass the source repository to LoadHasMany::load()'
                );
            }

            if (!method_exists($sourceRepository, $parentRelation->loader)) {
                throw new RuntimeException(
                    'Loader method "' . $parentRelation->loader . '" not found in repository: ' . $sourceRepository::class
                );
            }
            $loader = [$sourceRepository, $parentRelation->loader];
        }
        return $loader;
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
