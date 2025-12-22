<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
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
     * @param RepositoryInterface|null $sourceRepository The repository for the source entities (needed for loader)
     * @return EntityInterface[] All loaded children entities
     */
    public static function load(
        EntityInterface|array $entities,
        HasMany $parentRelation,
        RepositoryInterface $targetRepository,
        ?RepositoryInterface $sourceRepository = null
    ): array {

        $loader = null;
        // If loader is specified, use it instead of WHERE IN query
        if ($parentRelation->loader !== null) {
            if ($sourceRepository === null) {
                throw new RuntimeException(
                    'Source repository is required when using loader. ' .
                    'Please pass the source repository to LoadHasMany::load()'
                );
            }
            
            if (!method_exists($sourceRepository, $parentRelation->loader)) {
                throw new RuntimeException(
                    'Loader method "' . $parentRelation->loader . '" not found in repository: ' . $sourceRepository::class
                );
            }
            $loader = [$sourceRepository, $parentRelation->loader];
        }

        if (is_array($entities)) {
            return self::loadBatch($entities, $parentRelation, $targetRepository, $loader);
        }
        // Single entity
        return self::loadSingle($entities, $parentRelation, $targetRepository, $loader);
    }

    /**
     * Load HasMany relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $parentEntity,
        HasMany $parentRelation,
        RepositoryInterface $targetRepository,
        ?callable $loader = null
    ): array {
        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;

        if ($loader !== null) {
            $children = call_user_func($loader, $parentEntity);
        } else {
            // Find posts by foreign key
            $childRelation = $targetRepository->getRelation($parentRelation->mappedBy);
            $foreignKey = $targetRepository->getHydrator()->getColumnNameForProperty($childRelation->foreignKey)
                ?? $childRelation->foreignKey;
            $children = $targetRepository->find($parentEntity->getId(), $foreignKey, $parentRelation->orderBy);
        }
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
     * @param callable|null $loader
     * @return EntityInterface[] All loaded children entities
     */
    public static function loadBatch(
        array $parentEntities,
        HasMany $parentRelation,
        RepositoryInterface $targetRepository,
        ?callable $loader = null
    ): array {
        if (empty($parentEntities)) {
            return [];
        }

        // If loader is specified, use it instead of WHERE IN query
        if ($loader !== null) {           
            $children = call_user_func($loader, $parentEntities);
        } else {
            // Batch load all children using WHERE IN
            $childRelation = $targetRepository->getRelation($parentRelation->mappedBy);
            [$parentIds, ] = self::collectEntityIds($parentEntities);
            $foreignKey = $targetRepository->getHydrator()->getColumnNameForProperty($childRelation->foreignKey)
                ?? $childRelation->foreignKey;
            $query = $targetRepository->sqlQuery()
                ->whereIn($foreignKey, $parentIds);
            if ($parentRelation->orderBy !== null) {
                $query->orderBy($parentRelation->orderBy);
            }
            $children = $query->getResult();
        }

        // Use applyLoaderResult to map children to parents
        return self::applyLoaderResult($parentEntities, $children, $parentRelation, $targetRepository);
    }

    /**
     * Apply loader result for HasMany relation.
     * Groups loaded entities by parent ID using foreign key and sets them on parent entities.
     * 
     * @param EntityInterface|array<EntityInterface> $parentEntities
     * @param array<EntityInterface> $loadedChildren
     * @param HasMany $relation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded children entities
     */
    public static function applyLoaderResult(
        EntityInterface|array $parentEntities,
        array $loadedChildren,
        HasMany $relation,
        RepositoryInterface $targetRepository
    ): array {
        $parentEntities = is_array($parentEntities) ? $parentEntities : [$parentEntities];
        $parentProperty = $relation->propertyName;
        $childProperty = $relation->mappedBy;
        $childRelation = $targetRepository->getRelation($relation->mappedBy);
        
        // Collect parent IDs
        [$parentIds, $parentMap] = self::collectEntityIds($parentEntities);
        
        if (empty($parentIds)) {
            foreach ($parentEntities as $parentEntity) {
                $parentEntity->set($parentProperty, []);
            }
            return [];
        }
        
        // Group loaded children by parent ID using foreign key
        $childrenByParentId = self::groupEntitiesByForeignKey($loadedChildren, $childRelation->foreignKey);
        
        // Set children for each parent entity and set bidirectional links
        $allChildren = [];
        foreach ($parentMap as $parentId => $entity) {
            $childrenForParent = $childrenByParentId[$parentId] ?? [];
            
            // Set bidirectional link (child -> parent)
            foreach ($childrenForParent as $child) {
                $child->set($childProperty, $entity);
            }
            
            // Set children for all parent entities with this ID
            $entity->set($parentProperty, $childrenForParent);
            
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