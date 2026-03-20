<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

class LoadHasOne
{
    use RelationTrait;
    /**
     * Load HasOne relation for single entity or multiple entities.
     * 
     * @param \WScore\DecaORM\Contracts\EntityInterface|array<\WScore\DecaORM\Contracts\EntityInterface> $entities
     * @param HasOne $parentRelation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @param \WScore\DecaORM\Contracts\RepositoryInterface|null $sourceRepository The repository for the source entities (needed for loader)
     * @return \WScore\DecaORM\Contracts\EntityInterface[] All loaded children entities (array with 0 or 1 element per parent)
     */
    public static function load(
        EntityInterface|array $entities,
        HasOne $parentRelation,
        RepositoryInterface $targetRepository,
        ?RepositoryInterface $sourceRepository = null
    ): array {

        $loader = self::getLoader($parentRelation, $sourceRepository);

        if (is_array($entities)) {
            return self::loadBatch($entities, $parentRelation, $targetRepository, $loader);
        }        
        // Single entity
        return self::loadSingle($entities, $parentRelation, $targetRepository, $loader);
    }

    /**
     * Load HasOne relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $parentEntity,
        HasOne $parentRelation,
        RepositoryInterface $targetRepository,
        ?callable $loader = null
    ): array {
        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;
        $childRelation = $targetRepository->getRelation($parentRelation->mappedBy);

        if ($loader !== null) {
            $children = call_user_func($loader, $parentEntity);
            $children = $children instanceof EntityCollection ? $children : new EntityCollection((array)$children, $targetRepository);
        } else {
            $foreignKey = $targetRepository->getHydrator()->getColumnNameForProperty($childRelation->foreignKey)
                ?? $childRelation->foreignKey;
            $children = $targetRepository->find($parentEntity->getId(), $foreignKey);
        }

        if (count($children) === 0) {
            $parentEntity->setRaw($parentProperty, null);
            return [];
        }
        if (count($children) > 1) {
            throw new RuntimeException('HasOne relation must have only one child.');
        }
        $child = $children->getEntities()[0];
        $child->setRaw($childProperty, $parentEntity);
        $parentEntity->setRaw($parentProperty, $child);
        return [$child];
    }

    /**
     * Batch load HasOne relations for multiple entities.
     *
     * @param array<\WScore\DecaORM\Contracts\EntityInterface> $parentEntities
     * @param HasOne $parentRelation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @param callable|null $loader
     * @return \WScore\DecaORM\Contracts\EntityInterface[] All loaded children entities (array with 0 or 1 element per parent)
     */
    public static function loadBatch(
        array $parentEntities,
        HasOne $parentRelation,
        RepositoryInterface $targetRepository,
        ?callable $loader = null
    ): array {
        if (empty($parentEntities)) {
            return [];
        }

        // If loader is specified, use it instead of WHERE IN query
        if ($parentRelation->loader !== null) {
            $children = call_user_func($loader, $parentEntities);
            $children = $children instanceof EntityCollection ? $children : new EntityCollection((array)$children, $targetRepository);
        } else {
            // Batch load all children using WHERE IN
            $childRelation = $targetRepository->getRelation($parentRelation->mappedBy);
            $parentIds = array_keys((new EntityCollection($parentEntities))->getIdMap());
            $foreignKey = $targetRepository->getHydrator()->getColumnNameForProperty($childRelation->foreignKey)
                ?? $childRelation->foreignKey;
            $query = $targetRepository->sqlQuery()
                ->whereIn($foreignKey, $parentIds);
            $children = $query->getResult();
        }

        // Use applyLoaderResult to map children to parents
        return self::applyLoaderResult($parentEntities, $children, $parentRelation, $targetRepository);
    }

    /**
     * Apply loader result for HasOne relation.
     * Groups loaded entities by parent ID using foreign key and set them on parent entities.
     * 
     * @param EntityInterface|array<\WScore\DecaORM\Contracts\EntityInterface> $parentEntities
     * @param array<\WScore\DecaORM\Contracts\EntityInterface> $loadedChildren
     * @param HasOne $relation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @return \WScore\DecaORM\Contracts\EntityInterface[] All loaded children entities
     */
    public static function applyLoaderResult(
        EntityInterface|array $parentEntities,
        EntityCollection|array $loadedChildren,
        HasOne $relation,
        RepositoryInterface $targetRepository
    ): array {
        $parentEntities = is_array($parentEntities) ? $parentEntities : [$parentEntities];
        $parentProperty = $relation->propertyName;
        $childProperty = $relation->mappedBy;
        $childRelation = $targetRepository->getRelation($relation->mappedBy);
        
        $parentColl = new EntityCollection($parentEntities);
        $parentMap = $parentColl->getIdMap();
        $parentIds = array_keys($parentMap);

        if (empty($parentIds)) {
            foreach ($parentEntities as $parentEntity) {
                $parentEntity->setRaw($parentProperty, null);
            }
            return [];
        }
        
        $loadedChildren = $loadedChildren instanceof EntityCollection ? $loadedChildren : new EntityCollection($loadedChildren, $targetRepository);
        $childrenByParentId = $loadedChildren->groupByNonNullProperty($childRelation->foreignKey);

        $allChildren = [];
        foreach ($parentMap as $parentId => $entity) {
            $childrenForParent = isset($childrenByParentId[$parentId])
                ? $childrenByParentId[$parentId]->getEntities()
                : [];
            
            // HasOne should have at most one child
            if (count($childrenForParent) > 1) {
                throw new RuntimeException('HasOne relation must have only one child for parent ID: ' . $parentId);
            }
            
            $child = $childrenForParent[0] ?? null;

            $child?->setRaw($childProperty, $entity);
            
            // Set child for all parent entities with this ID
            $entity->setRaw($parentProperty, $child);
            
            if ($child !== null) {
                $allChildren[] = $child;
            }
        }
        
        // Set null for entities that had no children
        foreach ($parentEntities as $parentEntity) {
            if ($parentEntity->getRaw($parentProperty) === null && !isset($parentMap[$parentEntity->getId()])) {
                $parentEntity->setRaw($parentProperty, null);
            }
        }
        
        return $allChildren;
    }
}