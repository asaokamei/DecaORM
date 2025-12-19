<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

class LoadHasOne
{
    use RelationTrait;
    /**
     * Load HasOne relation for single entity or multiple entities.
     * 
     * @param EntityInterface|array<EntityInterface> $entities
     * @param HasOne $parentRelation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded children entities (array with 0 or 1 element per parent)
     */
    public static function load(
        EntityInterface|array $entities,
        HasOne $parentRelation,
        RepositoryInterface $targetRepository
    ): array {
        if (is_array($entities)) {
            return self::loadBatch($entities, $parentRelation, $targetRepository);
        }
        
        // Single entity
        return self::loadSingle($entities, $parentRelation, $targetRepository);
    }

    /**
     * Load HasOne relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $parentEntity,
        HasOne $parentRelation,
        RepositoryInterface $targetRepository
    ): array {
        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;
        $childRelation = $targetRepository->getRelation($parentRelation->mappedBy);

        // Find posts by foreign key
        $children = $targetRepository->find($parentEntity->getId(), $childRelation->foreignKey);

        if (empty($children)) {
            $parentEntity->set($parentProperty, null);
            return [];
        }
        if (count($children) > 1) {
            throw new RuntimeException('HasOne relation must have only one child.');
        }
        $child = $children[0];
        $child->set($childProperty, $parentEntity);
        $parentEntity->set($parentProperty, $child);
        return [$child];
    }

    /**
     * Batch load HasOne relations for multiple entities.
     * 
     * @param array<EntityInterface> $parentEntities
     * @param HasOne $parentRelation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded children entities (array with 0 or 1 element per parent)
     */
    public static function loadBatch(
        array $parentEntities,
        HasOne $parentRelation,
        RepositoryInterface $targetRepository
    ): array {
        if (empty($parentEntities)) {
            return [];
        }

        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;
        $childRelation = $targetRepository->getRelation($parentRelation->mappedBy);

        // Collect parent IDs (skip null IDs)
        [$parentIds, $parentMap] = self::collectEntityIds($parentEntities);

        if (empty($parentIds)) {
            // Set null for all entities
            foreach ($parentEntities as $parentEntity) {
                $parentEntity->set($parentProperty, null);
            }
            return [];
        }

        // Batch load all children using WHERE IN
        $allChildren = [];
        $query = $targetRepository->sqlQuery()
            ->whereIn($childRelation->foreignKey, $parentIds);
        $children = $query->getResult();

        // Group children by parent ID
        $childrenByParentId = self::groupEntitiesByForeignKey($children, $childRelation->foreignKey);

        // Set child for each parent entity and set bidirectional links
        foreach ($parentMap as $parentId => $entities) {
            $childrenForParent = $childrenByParentId[$parentId] ?? [];
            
            // HasOne should have at most one child
            if (count($childrenForParent) > 1) {
                throw new RuntimeException('HasOne relation must have only one child for parent ID: ' . $parentId);
            }
            
            $child = $childrenForParent[0] ?? null;

            if ($child !== null) {
                // Set bidirectional link (child -> parent)
                $child->set($childProperty, $entities[0]); // Use first entity as representative
            }

            // Set child for all parent entities with this ID
            foreach ($entities as $parentEntity) {
                $parentEntity->set($parentProperty, $child);
            }

            if ($child !== null) {
                $allChildren[] = $child;
            }
        }

        // Set null for entities that had no children
        foreach ($parentEntities as $parentEntity) {
            if ($parentEntity->get($parentProperty) === null && !isset($parentMap[$parentEntity->getId()])) {
                $parentEntity->set($parentProperty, null);
            }
        }

        return $allChildren;
    }
}