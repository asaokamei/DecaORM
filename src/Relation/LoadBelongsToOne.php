<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

class LoadBelongsToOne
{
    use RelationBelongsToTrait;

    /**
     * Load BelongsToOne relation for a single entity or multiple entities.
     * 
     * @param EntityInterface|EntityCollection<EntityInterface> $entities
     * @param BelongsToOne $childRelation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded parent entities (array with 0 or 1 elements per child)
     */
    public static function load(
        EntityInterface|EntityCollection $entities,
        BelongsToOne $childRelation,
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
     * Load BelongsToOne relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $childEntity,
        BelongsToOne $childRelation,
        RepositoryInterface $targetRepository
    ): array {
        $parentEntity = self::loadSingleEntity($childEntity, $childRelation, $targetRepository);

        // Set child on parent if inversedBy is specified and parent has HasOne
        if ($parentEntity && $childRelation->inversedBy !== null) {
            self::setChildOnParent($parentEntity, $childRelation->inversedBy, $childEntity, $targetRepository);
        }

        return $parentEntity ? [$parentEntity]: [];
    }

    /**
     * Batch load BelongsToOne relations for multiple entities.
     * 
     * @param EntityCollection<EntityInterface> $childEntities
     * @param BelongsToOne $childRelation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded parent entities (array with 0 or 1 elements per child)
     */
    public static function loadBatch(
        EntityCollection $childEntities,
        BelongsToOne $childRelation,
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

        // Set child on parent if inversedBy is specified and parent has HasOne
        if ($childRelation->inversedBy === null) {
            return $allParents;
        }
        $childrenByParentId = $children->groupBy($childRelation->foreignKey);
        foreach ($childrenByParentId as $parentId => $child) {
            if ($parents->hasId($parentId)) {
                if (count($child) > 1) {
                    throw new RuntimeException('BelongsToOne relation can only have one child per parent, but multiple children found for parent ID: ' . $parentId);
                }
                $parent = $parents->findById($parentId);
                self::setChildOnParent($parent, $childRelation->inversedBy, $child[0], $targetRepository);
            }
        }

        return $allParents;
    }

    /**
     * Set child entity on parent entity if parent has HasOne relation.
     *
     * - If parent has HasMany: Do not set (dangerous - assumes all children are loaded)
     * - If parent has HasOne: Set as single entity (not array)
     */
    private static function setChildOnParent(
        EntityInterface $parentEntity,
        string $parentPropertyName,
        EntityInterface $childEntity,
        RepositoryInterface $targetRepository
    ): void {
        $parentRelation = $targetRepository->getRelation($parentPropertyName);

        if ($parentRelation instanceof HasOne) {
            // Set as single entity (not array)
            $parentEntity->setRaw($parentPropertyName, $childEntity);
        }
    }
}