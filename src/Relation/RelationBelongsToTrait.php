<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

trait RelationBelongsToTrait
{
    private static function resolveOwnerKeyColumn(BelongsTo|BelongsToOne $childRelation, RepositoryInterface $targetRepository): string
    {
        if ($childRelation->ownerKey === null || $childRelation->ownerKey === '') {
            return $targetRepository->getHydrator()->getPrimaryKeyColumn();
        }
        return $targetRepository->getHydrator()->getColumnNameForProperty($childRelation->ownerKey)
            ?? $childRelation->ownerKey;
    }

    private static function resolveOwnerKeyProperty(BelongsTo|BelongsToOne $childRelation, RepositoryInterface $targetRepository): string
    {
        if ($childRelation->ownerKey === null || $childRelation->ownerKey === '') {
            return $targetRepository->getHydrator()->getPrimaryKey();
        }
        return $childRelation->ownerKey;
    }

    /**
     * Load BelongsTo relation for a single entity.
     */
    private static function loadSingleEntity(
        EntityInterface $childEntity,
        BelongsTo|BelongsToOne $childRelation,
        RepositoryInterface $targetRepository,
        ?callable $apply = null,
        ?RepositoryInterface $sourceRepository = null,
    ): ?EntityInterface {

        $ownerKeyCol = self::resolveOwnerKeyColumn($childRelation, $targetRepository);

        $matchValue = $childEntity->getRaw($childRelation->foreignKey);
        if ($matchValue === null) {
            $childEntity->setRaw($childRelation->propertyName, null);
            return null;
        }

        $query = $targetRepository->sqlQuery()
            ->where($ownerKeyCol, $matchValue);
        if ($apply !== null) {
            $apply($query, $childEntity, $childRelation, $targetRepository, $sourceRepository);
        }
        $parentEntity = $query->getResult();

        if (count($parentEntity) === 0) {
            $childEntity->setRaw($childRelation->propertyName, null);
            return null;
        }
        if (count($parentEntity) > 1) {
            throw new RuntimeException('BelongsTo relation must have only one parent.');
        }
        $parentEntity = $parentEntity[0];
        $childEntity->setRaw($childRelation->propertyName, $parentEntity);
        return $parentEntity;
    }

    /**
     * Retrieve and link parent entities for a collection of child entities based on the given relationship.
     *
     * @param EntityCollection $parents A collection of potential parent entities.
     * @param BelongsTo|BelongsToOne $childRelation The relationship definition linking child to parent.
     * @param EntityCollection<EntityInterface> $childEntities Child entities to link to their respective parents.
     * @return array An array of parent entities that were successfully linked to child entities.
     */
    public static function getParents(
        EntityCollection $parents,
        BelongsTo|BelongsToOne $childRelation,
        EntityCollection $childEntities,
        RepositoryInterface $targetRepository
    ): array
    {
        $ownerKeyProp = self::resolveOwnerKeyProperty($childRelation, $targetRepository);
        $parentMap = [];
        foreach ($parents as $p) {
            $key = $p->getRaw($ownerKeyProp);
            if ($key === null) {
                continue;
            }
            // If duplicates exist for the same ownerKey, keep first (apply should have prevented this).
            if (!array_key_exists((string) $key, $parentMap)) {
                $parentMap[(string) $key] = $p;
            }
        }

        $allParents = [];
        $childProperty = $childRelation->propertyName;
        foreach ($childEntities as $childEntity) {
            $matchValue = $childEntity->getRaw($childRelation->foreignKey);
            $key = $matchValue !== null ? (string) $matchValue : null;
            if ($key !== null && isset($parentMap[$key])) {
                $parent = $parentMap[$key];
                $childEntity->setRaw($childProperty, $parent);
                $allParents[] = $parent;
            } else {
                $childEntity->setRaw($childProperty, null);
            }
        }
        return $allParents;
    }

}