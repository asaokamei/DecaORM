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
        return self::applyLoaderResult($childEntities, $parents, $childRelation, $targetRepository);
    }

    /**
     * Apply loader result for BelongsTo relation.
     * Maps loaded parent entities to child entities using foreign key.
     * 
     * @param EntityInterface|array<EntityInterface> $childEntities
     * @param array<EntityInterface> $loadedParents
     * @param BelongsTo $relation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded parent entities
     */
    public static function applyLoaderResult(
        EntityInterface|array $childEntities,
        array $loadedParents,
        BelongsTo $relation,
        RepositoryInterface $targetRepository
    ): array {
        $childEntities = is_array($childEntities) ? $childEntities : [$childEntities];
        $childProperty = $relation->propertyName;
        $foreignKey = $relation->foreignKey;
        
        // Create a map of parent ID => parent entity
        $parentMap = self::createEntityMap($loadedParents);
        
        // Set parent for each child entity
        // Note: BelongsTo does not set child on parent (parent may have HasMany, which is dangerous)
        $allParents = [];
        foreach ($childEntities as $childEntity) {
            $parentId = $childEntity->get($foreignKey);
            if ($parentId !== null && isset($parentMap[$parentId])) {
                $childEntity->set($childProperty, $parentMap[$parentId]);
                $allParents[] = $parentMap[$parentId];
            } else {
                $childEntity->set($childProperty, null);
            }
        }
        
        return array_unique($allParents, SORT_REGULAR);
    }
}