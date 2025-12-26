<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\EntityCollection;
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
     * Retrieve and link parent entities for a collection of child entities based on the given relationship.
     *
     * @param EntityCollection $parents A collection of potential parent entities.
     * @param BelongsTo|BelongsToOne $childRelation The relationship definition linking child to parent.
     * @param array $childEntities An array of child entities to link to their respective parents.
     * @return array An array of parent entities that were successfully linked to child entities.
     */
    public static function getParents(EntityCollection $parents, BelongsTo|BelongsToOne $childRelation, array $childEntities): array
    {
        // Set parent for each child entity
        // Note: BelongsTo does not set child on parent (parent may have HasMany, which is dangerous)
        $allParents = [];
        $childProperty = $childRelation->propertyName;
        foreach ($childEntities as $childEntity) {
            $parentId = $childEntity->get($childRelation->foreignKey);
            if ($parentId !== null && $parents->hasId($parentId)) {
                $parent = $parents->findById($parentId);
                $childEntity->set($childProperty, $parent);
                $allParents[] = $parent;
            } else {
                $childEntity->set($childProperty, null);
            }
        }
        return $allParents;
    }

}