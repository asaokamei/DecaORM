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
     * @param RepositoryInterface|null $sourceRepository The repository for the source entities (needed for loader)
     * @return EntityInterface[] All loaded children entities (array with 0 or 1 element per parent)
     */
    public static function load(
        EntityInterface|array $entities,
        HasOne $parentRelation,
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
        } else {
            $foreignKey = $targetRepository->getHydrator()->getColumnNameForProperty($childRelation->foreignKey)
                ?? $childRelation->foreignKey;
            $children = $targetRepository->find($parentEntity->getId(), $foreignKey);
        }

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
     * @param callable|null $loader
     * @return EntityInterface[] All loaded children entities (array with 0 or 1 element per parent)
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
        } else {
            // Batch load all children using WHERE IN
            $childRelation = $targetRepository->getRelation($parentRelation->mappedBy);
            [$parentIds, ] = self::collectEntityIds($parentEntities);
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
     * Groups loaded entities by parent ID using foreign key and sets them on parent entities.
     * 
     * @param EntityInterface|array<EntityInterface> $parentEntities
     * @param array<EntityInterface> $loadedChildren
     * @param HasOne $relation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded children entities
     */
    public static function applyLoaderResult(
        EntityInterface|array $parentEntities,
        array $loadedChildren,
        HasOne $relation,
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
                $parentEntity->set($parentProperty, null);
            }
            return [];
        }
        
        // Group loaded children by parent ID using foreign key
        $childrenByParentId = self::groupEntitiesByForeignKey($loadedChildren, $childRelation->foreignKey);
        
        // Set child for each parent entity and set bidirectional links
        $allChildren = [];
        foreach ($parentMap as $parentId => $entity) {
            $childrenForParent = $childrenByParentId[$parentId] ?? [];
            
            // HasOne should have at most one child
            if (count($childrenForParent) > 1) {
                throw new RuntimeException('HasOne relation must have only one child for parent ID: ' . $parentId);
            }
            
            $child = $childrenForParent[0] ?? null;

            $child?->set($childProperty, $entity);
            
            // Set child for all parent entities with this ID
            $entity->set($parentProperty, $child);
            
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