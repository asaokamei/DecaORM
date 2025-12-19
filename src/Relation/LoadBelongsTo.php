<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

class LoadBelongsTo
{
    use RelationTrait;
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
        $parentId = $childEntity->get($childRelation->foreignKey);
        $parentEntity = $targetRepository->find($parentId);
        if (empty($parentEntity)) {
            $childEntity->set($childRelation->propertyName, null);
            return [];
        } elseif (count($parentEntity) > 1) {
            throw new RuntimeException('BelongsTo relation must have only one parent.');
        } else {
            $parentEntity = $parentEntity[0];
            $childEntity->set($childRelation->propertyName, $parentEntity);
            // Note: BelongsTo does not set child on parent (parent may have HasMany, which is dangerous)
            return [$parentEntity];
        }
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

        $childProperty = $childRelation->propertyName;
        $foreignKey = $childRelation->foreignKey;
        $primaryKey = $targetRepository->getPrimaryKeyColumn();

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
        $allParents = [];
        $query = $targetRepository->sqlQuery()
            ->whereIn($primaryKey, $parentIds);
        $parents = $query->getResult();

        // Create a map of parentId => parent entity
        $parentMap = [];
        foreach ($parents as $parent) {
            $parentId = $parent->getId();
            if ($parentId !== null) {
                if (isset($parentMap[$parentId])) {
                    throw new RuntimeException('BelongsTo relation must have only one parent for ID: ' . $parentId);
                }
                $parentMap[$parentId] = $parent;
            }
        }

        // Set parent for each child entity
        // Note: BelongsTo does not set child on parent (parent may have HasMany, which is dangerous)
        foreach ($childrenByParentId as $parentId => $children) {
            $parent = $parentMap[$parentId] ?? null;
            
            foreach ($children as $childEntity) {
                $childEntity->set($childProperty, $parent);
            }

            if ($parent !== null) {
                $allParents[] = $parent;
            }
        }

        return $allParents;
    }
}