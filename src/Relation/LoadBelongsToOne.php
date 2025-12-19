<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

class LoadBelongsToOne
{
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
        $parentId = $childEntity->get($childRelation->foreignKey);
        $parentEntity = $targetRepository->find($parentId);
        if (empty($parentEntity)) {
            $childEntity->set($childRelation->propertyName, null);
            return [];
        } elseif (count($parentEntity) > 1) {
            throw new RuntimeException('BelongsToOne relation must have only one parent.');
        } else {
            $parentEntity = $parentEntity[0];
            $childEntity->set($childRelation->propertyName, $parentEntity);
            
            // Set child on parent if inversedBy is specified and parent has HasOne
            if ($childRelation->inversedBy !== null) {
                self::setChildOnParent($parentEntity, $childRelation->inversedBy, $childEntity, $targetRepository);
            }
            
            return [$parentEntity];
        }
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
        $primaryKey = $targetRepository->getPrimaryKeyColumn();

        // Collect parent IDs from child entities (skip null foreign keys)
        $parentIds = [];
        $childrenByParentId = []; // parentId => [child entities with this parentId]
        $childrenWithoutParent = []; // child entities with null foreign key

        foreach ($childEntities as $childEntity) {
            $parentId = $childEntity->get($foreignKey);
            if ($parentId === null) {
                $childrenWithoutParent[] = $childEntity;
                continue;
            }
            if (!isset($childrenByParentId[$parentId])) {
                $childrenByParentId[$parentId] = [];
                $parentIds[] = $parentId;
            }
            $childrenByParentId[$parentId][] = $childEntity;
        }

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
        $sql = $query->getSql();
        $data = $query->getParameters();
        $parents = $targetRepository->fetch($sql, $data);

        // Create a map of parentId => parent entity
        $parentMap = [];
        foreach ($parents as $parent) {
            $parentId = $parent->getId();
            if ($parentId !== null) {
                if (isset($parentMap[$parentId])) {
                    throw new RuntimeException('BelongsToOne relation must have only one parent for ID: ' . $parentId);
                }
                $parentMap[$parentId] = $parent;
            }
        }

        // Set parent for each child entity and optionally set child on parent
        foreach ($childrenByParentId as $parentId => $children) {
            $parent = $parentMap[$parentId] ?? null;
            
            foreach ($children as $childEntity) {
                $childEntity->set($childProperty, $parent);
            }

            // Set child on parent if inversedBy is specified and parent has HasOne
            if ($parent !== null && $childRelation->inversedBy !== null) {
                // BelongsToOne should have only one child per parent
                if (count($children) > 1) {
                    throw new RuntimeException('BelongsToOne relation can only have one child per parent, but multiple children found for parent ID: ' . $parentId);
                }
                self::setChildOnParent($parent, $childRelation->inversedBy, $children[0], $targetRepository);
            }

            if ($parent !== null) {
                $allParents[] = $parent;
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
            return;
        }
        
        // If parent has HasMany or unknown relation type, do not set
        // (HasMany is dangerous - assumes all children are loaded)
    }
}

