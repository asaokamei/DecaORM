<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
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
     * @param EntityInterface|EntityCollection<EntityInterface> $entities
     * @param HasOne $parentRelation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @param \WScore\DecaORM\Contracts\RepositoryInterface|null $sourceRepository The repository for the source entities (needed for loader)
     * @return \WScore\DecaORM\Contracts\EntityInterface[] All loaded children entities (array with 0 or 1 element per parent)
     */
    public static function load(
        EntityInterface|EntityCollection $entities,
        HasOne $parentRelation,
        RepositoryInterface $targetRepository,
        ?RepositoryInterface $sourceRepository = null
    ): array {
        $sourceFilter = self::resolveSourceFilter($parentRelation, $sourceRepository);
        $targetScope = self::resolveTargetScope($parentRelation, $targetRepository);

        if ($entities instanceof EntityInterface) {
            return self::loadSingle($entities, $parentRelation, $targetRepository, $sourceFilter, $sourceRepository, $targetScope);
        }
        if (count($entities) === 0) {
            return [];
        }
        if (count($entities) === 1) {
            $first = $entities->first();
            if (!$first instanceof EntityInterface) {
                return [];
            }
            return self::loadSingle($first, $parentRelation, $targetRepository, $sourceFilter, $sourceRepository, $targetScope);
        }
        return self::loadBatch($entities, $parentRelation, $targetRepository, $sourceFilter, $sourceRepository, $targetScope);
    }

    /**
     * Load HasOne relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $parentEntity,
        HasOne $parentRelation,
        RepositoryInterface $targetRepository,
        ?callable $sourceFilter = null,
        ?RepositoryInterface $sourceRepository = null,
        ?callable $targetScope = null,
    ): array {
        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;

        $parentRepo = self::resolveParentRepositoryForInverse($parentEntity, $sourceRepository);
        $children = MappedByQuery::fetch(
            $targetRepository,
            $parentRelation->mappedBy,
            $parentEntity,
            $parentRepo,
            null,
            $sourceFilter,
            $targetScope,
        );

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
     * @param EntityCollection<EntityInterface> $parentEntities
     * @param HasOne $parentRelation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @param callable|null $sourceFilter
     * @param RepositoryInterface|null $sourceRepository
     * @param callable|null $targetScope
     * @return \WScore\DecaORM\Contracts\EntityInterface[] All loaded children entities (array with 0 or 1 element per parent)
     */
    public static function loadBatch(
        EntityCollection $parentEntities,
        HasOne $parentRelation,
        RepositoryInterface $targetRepository,
        ?callable $sourceFilter = null,
        ?RepositoryInterface $sourceRepository = null,
        ?callable $targetScope = null,
    ): array {
        if (count($parentEntities) === 0) {
            return [];
        }

        $parentRepo = self::resolveParentRepositoryForInverse($parentEntities, $sourceRepository);
        $children = MappedByQuery::fetch(
            $targetRepository,
            $parentRelation->mappedBy,
            $parentEntities,
            $parentRepo,
            null,
            $sourceFilter,
            $targetScope,
        );

        // Use applyLoaderResult to map children to parents
        return self::applyLoaderResult($parentEntities, $children, $parentRelation, $targetRepository, $parentRepo);
    }

    /**
     * Apply loader result for HasOne relation.
     * Groups loaded entities by parent ID using foreign key and set them on parent entities.
     * 
     * @param EntityCollection<EntityInterface> $parentEntities
     * @param EntityCollection|array<\WScore\DecaORM\Contracts\EntityInterface> $loadedChildren
     * @param HasOne $relation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @return \WScore\DecaORM\Contracts\EntityInterface[] All loaded children entities
     */
    public static function applyLoaderResult(
        EntityCollection $parentEntities,
        EntityCollection|array $loadedChildren,
        HasOne $relation,
        RepositoryInterface $targetRepository,
        RepositoryInterface $parentRepository
    ): array {
        $parentProperty = $relation->propertyName;
        $childProperty = $relation->mappedBy;
        $childRelation = $targetRepository->getRelation($relation->mappedBy);

        if (!($childRelation instanceof BelongsTo || $childRelation instanceof BelongsToOne)) {
            return [];
        }

        $parentMap = MappedByQuery::buildParentMapByInverse($parentEntities, $childRelation, $parentRepository);

        if (empty($parentMap)) {
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
        
        return $allChildren;
    }
}