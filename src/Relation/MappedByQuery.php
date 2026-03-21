<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\MorphTo;
use WScore\DecaORM\Attribute\MorphToOne;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\EntityCollection;

/**
 * Loads child entities from the owning (FK) side for a HasMany / HasOne parent,
 * using the child's relation property named by {@see HasMany::$mappedBy} / {@see HasOne::$mappedBy}.
 *
 * Kept out of {@see RepositoryInterface} so repositories stay a thin persistence API; loaders call this helper.
 */
final class MappedByQuery
{
    public static function fetch(
        RepositoryInterface $childRepository,
        string $mappedByPropertyName,
        EntityInterface|EntityCollection $parents,
        RepositoryInterface $parentRepository,
        ?string $orderBy = null,
    ): EntityCollection {
        $inverse = $childRepository->getRelation($mappedByPropertyName);
        if ($inverse === null) {
            throw new RuntimeException('Relation not found for mappedBy property: ' . $mappedByPropertyName);
        }

        if ($inverse instanceof BelongsTo || $inverse instanceof BelongsToOne) {
            if ($parents instanceof EntityInterface) {
                $parentId = $parents->getId();
                if ($parentId === null) {
                    return new EntityCollection([], $childRepository);
                }
                $fkCol = $childRepository->getHydrator()->getColumnNameForProperty($inverse->foreignKey)
                    ?? $inverse->foreignKey;
                return $childRepository->find($parentId, $fkCol, $orderBy);
            }
            $parentIds = array_keys($parents->getIdMap());
            if ($parentIds === []) {
                return new EntityCollection([], $childRepository);
            }
            $fkCol = $childRepository->getHydrator()->getColumnNameForProperty($inverse->foreignKey)
                ?? $inverse->foreignKey;
            return $childRepository->find($parentIds, $fkCol, $orderBy);
        }

        if ($inverse instanceof MorphTo || $inverse instanceof MorphToOne) {
            $parentClass = $parentRepository->getHydrator()->getEntityClass();
            $disc = $inverse->discriminatorForClass($parentClass);
            $fkCol = $childRepository->getHydrator()->getColumnNameForProperty($inverse->foreignKey)
                ?? $inverse->foreignKey;
            $typeCol = $childRepository->getHydrator()->getColumnNameForProperty($inverse->typeColumn)
                ?? $inverse->typeColumn;

            if ($parents instanceof EntityInterface) {
                $parentId = $parents->getId();
                if ($parentId === null) {
                    return new EntityCollection([], $childRepository);
                }
                $query = $childRepository->sqlQuery()
                    ->where($typeCol, $disc)
                    ->where($fkCol, $parentId);
                if ($orderBy !== null) {
                    $query->orderBy($orderBy);
                }
                return $query->getResult();
            }
            $parentIds = array_keys($parents->getIdMap());
            if ($parentIds === []) {
                return new EntityCollection([], $childRepository);
            }
            $query = $childRepository->sqlQuery()
                ->where($typeCol, $disc)
                ->whereIn($fkCol, $parentIds);
            if ($orderBy !== null) {
                $query->orderBy($orderBy);
            }
            return $query->getResult();
        }

        throw new RuntimeException('Unsupported child relation for HasMany/HasOne mappedBy: ' . get_class($inverse));
    }
}
