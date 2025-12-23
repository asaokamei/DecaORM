<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

trait RelationBelongsToTrait
{
    /**
     * Load BelongsTo relation for a single entity.
     */
    private static function loadSingleEntity(
        EntityInterface $childEntity,
        BelongsTo|BelongsToOne $childRelation,
        RepositoryInterface $targetRepository
    ): ?EntityInterface {

        $parentId = $childEntity->get($childRelation->foreignKey);
        $parentEntity = $targetRepository->find($parentId);
        if (empty($parentEntity)) {
            $childEntity->set($childRelation->propertyName, null);
            return null;
        }
        if (count($parentEntity) > 1) {
            throw new RuntimeException('BelongsTo relation must have only one parent.');
        }
        $parentEntity = $parentEntity[0];
        $childEntity->set($childRelation->propertyName, $parentEntity);
        return $parentEntity;
    }

    /**
     * Apply loader result for BelongsTo relation.
     * Maps loaded parent entities to child entities using foreign key.
     *
     * @param EntityInterface|array<EntityInterface> $childEntities
     * @param array<EntityInterface> $loadedParents
     * @param BelongsTo|BelongsToOne $relation
     * @return EntityInterface[] All loaded parent entities
     */
    private static function applyLoaderResult(
        EntityInterface|array $childEntities,
        array $loadedParents,
        BelongsTo|BelongsToOne $relation
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