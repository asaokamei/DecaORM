<?php

namespace WScore\DecaORM\Relation;

use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

class LoadBelongsTo
{
    use RelationBelongsToTrait;

    /**
     * Load BelongsTo relation for single entity or multiple entities.
     * 
     * @param EntityInterface|EntityCollection<EntityInterface> $entities
     * @param BelongsTo $childRelation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded parent entities (array with 0 or 1 element per child)
     */
    public static function load(
        EntityInterface|EntityCollection $entities,
        BelongsTo $childRelation,
        RepositoryInterface $targetRepository
    ): array {
        if ($entities instanceof EntityInterface) {
            return self::loadSingle($entities, $childRelation, $targetRepository);
        }
        if (count($entities) === 0) {
            return [];
        }
        if (count($entities) === 1) {
            $first = $entities->first();
            if (!$first instanceof EntityInterface) {
                return [];
            }
            return self::loadSingle($first, $childRelation, $targetRepository);
        }
        return self::loadBatch($entities, $childRelation, $targetRepository);
    }

    /**
     * Load BelongsTo relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $childEntity,
        BelongsTo $childRelation,
        RepositoryInterface $targetRepository
    ): array {

        $parentEntity = self::loadSingleEntity($childEntity, $childRelation, $targetRepository);
        // Note: BelongsTo does not set child on parent (parent may have HasMany, which is dangerous)
        return $parentEntity ? [$parentEntity]: [];
    }

    /**
     * Batch load BelongsTo relations for multiple entities.
     * 
     * @param EntityCollection<EntityInterface> $childEntities
     * @param BelongsTo $childRelation
     * @param RepositoryInterface $targetRepository
     * @return \WScore\DecaORM\Contracts\EntityInterface[] All loaded parent entities (array with 0 or 1 element per child)
     */
    public static function loadBatch(
        EntityCollection $childEntities,
        BelongsTo $childRelation,
        RepositoryInterface $targetRepository
    ): array {
        if (count($childEntities) === 0) {
            return [];
        }

        $children = $childEntities;
        $parentIds = $children->getValues($childRelation->foreignKey);
        if (empty($parentIds)) {
            return [];
        }

        // Batch load all parents using WHERE IN
        $parents = $targetRepository
            ->sqlQuery()
            ->whereIn($targetRepository->getHydrator()->getPrimaryKeyColumn(), $parentIds)
            ->getResult();
        $allParents = self::getParents($parents, $childRelation, $children);

        return array_unique($allParents, SORT_REGULAR);
    }

}