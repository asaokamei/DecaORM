<?php

namespace WScore\DecaORM\Relation;

use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

class LoadHasMany
{
    use RelationTrait;
    /**
     * Load HasMany relation for single entity or multiple entities.
     * 
     * @param EntityInterface|EntityCollection<EntityInterface> $entities
     * @param HasMany $parentRelation
     * @param RepositoryInterface $targetRepository
     * @param RepositoryInterface|null $sourceRepository The repository for the source entities (needed for loader)
     * @return EntityInterface[] All loaded children entities
     */
    public static function load(
        EntityInterface|EntityCollection $entities,
        HasMany $parentRelation,
        RepositoryInterface $targetRepository,
        ?RepositoryInterface $sourceRepository = null
    ): array {

        $loader = self::getLoader($parentRelation, $sourceRepository);

        if ($entities instanceof EntityInterface) {
            return self::loadSingle($entities, $parentRelation, $targetRepository, $loader, $sourceRepository);
        }
        if (count($entities) === 0) {
            return [];
        }
        if (count($entities) === 1) {
            $first = $entities->first();
            if (!$first instanceof EntityInterface) {
                return [];
            }
            return self::loadSingle($first, $parentRelation, $targetRepository, $loader, $sourceRepository);
        }
        return self::loadBatch($entities, $parentRelation, $targetRepository, $loader, $sourceRepository);
    }

    /**
     * Load HasMany relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $parentEntity,
        HasMany $parentRelation,
        RepositoryInterface $targetRepository,
        ?callable $loader = null,
        ?RepositoryInterface $sourceRepository = null
    ): array {
        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;

        if ($loader !== null) {
            $children = call_user_func($loader, $parentEntity);
            $children = $children instanceof EntityCollection ? $children : new EntityCollection((array)$children, $targetRepository);
        } else {
            $parentRepo = self::resolveParentRepositoryForInverse($parentEntity, $sourceRepository);
            $children = MappedByQuery::fetch(
                $targetRepository,
                $parentRelation->mappedBy,
                $parentEntity,
                $parentRepo,
                $parentRelation->orderBy
            );
        }
        if (count($children) === 0) {
            $parentEntity->setRaw($parentProperty, new EntityCollection([], $targetRepository));
            return [];
        }

        // Set the bidirectional link (post -> user)
        foreach ($children as $child) {
            $child->setRaw($childProperty, $parentEntity);
        }

        $parentEntity->setRaw($parentProperty, $children);
        return $children->getEntities();
    }

    /**
     * Batch load HasMany relations for multiple entities.
     *
     * @param EntityCollection<EntityInterface> $parentEntities
     * @param HasMany $parentRelation
     * @param RepositoryInterface $targetRepository
     * @param callable|null $loader
     * @return EntityInterface[] All loaded children entities
     */
    public static function loadBatch(
        EntityCollection $parentEntities,
        HasMany $parentRelation,
        RepositoryInterface $targetRepository,
        ?callable $loader = null,
        ?RepositoryInterface $sourceRepository = null
    ): array {
        if (count($parentEntities) === 0) {
            return [];
        }

        // If loader is specified, use it instead of WHERE IN query
        if ($loader !== null) {           
            $children = call_user_func($loader, $parentEntities);
            $children = $children instanceof EntityCollection ? $children : new EntityCollection((array)$children, $targetRepository);
        } else {
            $parentRepo = self::resolveParentRepositoryForInverse($parentEntities, $sourceRepository);
            $children = MappedByQuery::fetch(
                $targetRepository,
                $parentRelation->mappedBy,
                $parentEntities,
                $parentRepo,
                $parentRelation->orderBy
            );
        }

        // Use applyLoaderResult to map children to parents
        return self::applyLoaderResult($parentEntities, $children, $parentRelation, $targetRepository);
    }

    /**
     * Apply loader result for HasMany relation.
     * Groups loaded entities by parent ID using foreign key and set them on parent entities.
     * 
     * @param EntityCollection<EntityInterface> $parentEntities
     * @param EntityCollection|array<EntityInterface> $loadedChildren
     * @param HasMany $relation
     * @param RepositoryInterface $targetRepository
     * @return EntityInterface[] All loaded children entities
     */
    public static function applyLoaderResult(
        EntityCollection $parentEntities,
        EntityCollection|array $loadedChildren,
        HasMany $relation,
        RepositoryInterface $targetRepository
    ): array {
        $parentProperty = $relation->propertyName;
        $childProperty = $relation->mappedBy;
        $childRelation = $targetRepository->getRelation($relation->mappedBy);
        $loadedChildren = $loadedChildren instanceof EntityCollection ? $loadedChildren : new EntityCollection($loadedChildren, $targetRepository);
        
        $parentMap = $parentEntities->getIdMap();
        $parentIds = array_keys($parentMap);

        if (empty($parentIds)) {
            foreach ($parentEntities as $parentEntity) {
                $parentEntity->setRaw($parentProperty, new EntityCollection([], $targetRepository));
            }
            return [];
        }
        
        $childrenByParentId = $loadedChildren->groupByNonNullProperty($childRelation->foreignKey);

        $allChildren = [];
        foreach ($parentMap as $parentId => $entity) {
            $childrenForParent = isset($childrenByParentId[$parentId])
                ? $childrenByParentId[$parentId]->getEntities()
                : [];
            
            // Set bidirectional link (child -> parent)
            foreach ($childrenForParent as $child) {
                $child->setRaw($childProperty, $entity);
            }
            
            // Set children for all parent entities with this ID
            $entity->setRaw($parentProperty, new EntityCollection($childrenForParent, $targetRepository));
            
            $allChildren = array_merge($allChildren, $childrenForParent);
        }
        
        // Set empty collections for entities that had no children
        foreach ($parentEntities as $parentEntity) {
            if ($parentEntity->getRaw($parentProperty) === null) {
                $parentEntity->setRaw($parentProperty, new EntityCollection([], $targetRepository));
            }
        }
        
        return $allChildren;
    }
}