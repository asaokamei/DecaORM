<?php

namespace WScore\DecaORM\Relation;

use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

class LoadBelongsTo
{
    use RelationTrait;
    use RelationBelongsToTrait;

    /**
     * Load BelongsTo relation for single entity or multiple entities.
     * 
     * @param EntityInterface|array<EntityInterface> $entities
     * @param BelongsTo $childRelation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded parent entities (array with 0 or 1 element per child)
     */
    public static function load(
        EntityInterface|array $entities,
        BelongsTo $childRelation,
        RepositoryInterface $targetRepository
    ): array {
        if (is_array($entities)) {
            return self::loadBatch($entities, $childRelation, $targetRepository);
        }
        
        // Single entity
        return self::loadSingle($entities, $childRelation, $targetRepository);
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
     * @param array<EntityInterface> $childEntities
     * @param BelongsTo $childRelation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded parent entities (array with 0 or 1 element per child)
     */
    public static function loadBatch(
        array $childEntities,
        BelongsTo $childRelation,
        RepositoryInterface $targetRepository
    ): array {
        if (empty($childEntities)) {
            return [];
        }

        $children = new EntityCollection($childEntities);
        $parentIds = $children->getValues($childRelation->foreignKey);
        if (empty($parentIds)) {
            return [];
        }

        // Batch load all parents using WHERE IN
        $parents = $targetRepository
            ->sqlQuery()
            ->whereIn($targetRepository->getPrimaryKeyColumn(), $parentIds)
            ->getCollection();
        $allParents = self::getParents($parents, $childRelation, $childEntities);

        return array_unique($allParents, SORT_REGULAR);
    }

}