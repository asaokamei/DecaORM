<?php

namespace WScore\DecaORM\Relation;

use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

class LoadHasMany
{
    use RelationTrait;
    /**
     * Load HasMany relation for single entity or multiple entities.
     * 
     * @param EntityInterface|array<EntityInterface> $entities
     * @param HasMany $parentRelation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded children entities
     */
    public static function load(
        EntityInterface|array $entities,
        HasMany $parentRelation,
        RepositoryInterface $targetRepository
    ): array {
        if (is_array($entities)) {
            return self::loadBatch($entities, $parentRelation, $targetRepository);
        }
        
        // Single entity
        return self::loadSingle($entities, $parentRelation, $targetRepository);
    }

    /**
     * Load HasMany relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $parentEntity,
        HasMany $parentRelation,
        RepositoryInterface $targetRepository
    ): array {
        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;
        $childRelation = $targetRepository->getRelation($parentRelation->mappedBy);

        // Find posts by foreign key
        $children = $targetRepository->find($parentEntity->getId(), $childRelation->foreignKey, $parentRelation->orderBy);

        if (empty($children)) {
            $parentEntity->set($parentProperty, []);
            return [];
        }

        // Set the bidirectional link (post -> user)
        foreach ($children as $child) {
            $child->set($childProperty, $parentEntity);
        }

        $parentEntity->set($parentProperty, $children);
        return $children;
    }

    /**
     * Batch load HasMany relations for multiple entities.
     * 
     * @param array<EntityInterface> $parentEntities
     * @param HasMany $parentRelation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded children entities
     */
    public static function loadBatch(
        array $parentEntities,
        HasMany $parentRelation,
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
            // Set empty arrays for all entities
            foreach ($parentEntities as $parentEntity) {
                $parentEntity->set($parentProperty, []);
            }
            return [];
        }

        // Batch load all children using WHERE IN
        $query = $targetRepository->sqlQuery()
            ->whereIn($childRelation->foreignKey, $parentIds);
        if ($parentRelation->orderBy !== null) {
            $query->orderBy($parentRelation->orderBy);
        }
        $children = $query->getResult();

        // Group children by parent ID
        $childrenByParentId = self::groupEntitiesByForeignKey($children, $childRelation->foreignKey);

        // Set children for each parent entity and set bidirectional links
        $allChildren = [];
        foreach ($parentMap as $parentId => $entities) {
            $childrenForParent = $childrenByParentId[$parentId] ?? [];
            
            // Set bidirectional link (child -> parent)
            foreach ($childrenForParent as $child) {
                $child->set($childProperty, $entities[0]); // Use first entity as representative
            }

            // Set children for all parent entities with this ID
            foreach ($entities as $parentEntity) {
                $parentEntity->set($parentProperty, $childrenForParent);
            }

            $allChildren = array_merge($allChildren, $childrenForParent);
        }

        // Set empty arrays for entities that had no children
        foreach ($parentEntities as $parentEntity) {
            if (!$parentEntity->get($parentProperty)) {
                $parentEntity->set($parentProperty, []);
            }
        }

        return $allChildren;
    }
}