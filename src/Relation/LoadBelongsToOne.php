<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

class LoadBelongsToOne
{
    use RelationTrait;
    use RelationBelongsToTrait;

    /**
     * Load BelongsToOne relation for single entity or multiple entities.
     * 
     * @param EntityInterface|array<EntityInterface> $entities
     * @param BelongsToOne $childRelation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded parent entities (array with 0 or 1 element per child)
     */
    public static function load(
        EntityInterface|array $entities,
        BelongsToOne $childRelation,
        RepositoryInterface $targetRepository
    ): array {
        if (is_array($entities)) {
            return self::loadBatch($entities, $childRelation, $targetRepository);
        }
        
        // Single entity
        return self::loadSingle($entities, $childRelation, $targetRepository);
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
     * @param array<EntityInterface> $childEntities
     * @param BelongsToOne $childRelation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded parent entities (array with 0 or 1 element per child)
     */
    public static function loadBatch(
        array $childEntities,
        BelongsToOne $childRelation,
        RepositoryInterface $targetRepository
    ): array {
        if (empty($childEntities)) {
            return [];
        }

        $childProperty = $childRelation->propertyName;
        $foreignKey = $childRelation->foreignKey;

        // Collect parent IDs from child entities (skip null foreign keys)
        [$parentIds, $childrenByParentId, $childrenWithoutParent] = self::collectParentIdsFromChildren($childEntities, $foreignKey);

        // Set null for children without parent ID
        foreach ($childrenWithoutParent as $childEntity) {
            $childEntity->set($childProperty, null);
        }

        if (empty($parentIds)) {
            return [];
        }

        // Batch load all parents using WHERE IN
        $primaryKey = $targetRepository->getPrimaryKeyColumn();
        $query = $targetRepository->sqlQuery()
            ->whereIn($primaryKey, $parentIds);
        $parents = $query->getResult();

        // Use applyLoaderResult to map parents to children
        $allParents = self::applyLoaderResult($childEntities, $parents, $childRelation);

        // Set child on parent if inversedBy is specified and parent has HasOne
        if ($childRelation->inversedBy !== null) {
            foreach ($childrenByParentId as $parentId => $children) {
                // Find parent entity
                $parent = null;
                foreach ($parents as $p) {
                    if ($p->getId() === $parentId) {
                        $parent = $p;
                        break;
                    }
                }
                
                if ($parent !== null) {
                    // BelongsToOne should have only one child per parent
                    if (count($children) > 1) {
                        throw new RuntimeException('BelongsToOne relation can only have one child per parent, but multiple children found for parent ID: ' . $parentId);
                    }
                    self::setChildOnParent($parent, $childRelation->inversedBy, $children[0], $targetRepository);
                }
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
            $parentEntity->set($parentPropertyName, $childEntity);
        }
    }
}