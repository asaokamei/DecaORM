<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Contracts\RepositoryInterface;

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
}
