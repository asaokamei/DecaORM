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
    /**
     * Resolve parent-side property name used for matching child foreign key.
     */
    public static function resolveParentMatchProperty(BelongsTo|BelongsToOne $inverse, RepositoryInterface $parentRepository): string
    {
        if ($inverse->ownerKey === null || $inverse->ownerKey === '') {
            return $parentRepository->getHydrator()->getPrimaryKey();
        }
        return $inverse->ownerKey;
    }

    /**
     * @return array<int|string>
     */
    public static function getParentMatchValues(
        EntityInterface|EntityCollection $parents,
        BelongsTo|BelongsToOne $inverse,
        RepositoryInterface $parentRepository
    ): array {
        $parentKeyProp = self::resolveParentMatchProperty($inverse, $parentRepository);

        if ($parents instanceof EntityInterface) {
            $parentValue = $parents->getRaw($parentKeyProp);
            if ($parentValue === null) {
                return [];
            }
            return [$parentValue];
        }

        $values = [];
        foreach ($parents as $parent) {
            $value = $parent->getRaw($parentKeyProp);
            if ($value === null) {
                continue;
            }
            $values[] = $value;
        }
        return array_values(array_unique($values, SORT_REGULAR));
    }

    /**
     * @param EntityCollection<EntityInterface> $parents
     * @return array<int|string, EntityInterface>
     */
    public static function buildParentMapByInverse(
        EntityCollection $parents,
        BelongsTo|BelongsToOne $inverse,
        RepositoryInterface $parentRepository
    ): array {
        $parentKeyProp = self::resolveParentMatchProperty($inverse, $parentRepository);
        $parentMap = [];
        foreach ($parents as $parent) {
            $key = $parent->getRaw($parentKeyProp);
            if ($key === null) {
                continue;
            }
            if (!array_key_exists((string) $key, $parentMap)) {
                $parentMap[(string) $key] = $parent;
            }
        }
        return $parentMap;
    }

    public static function fetch(
        RepositoryInterface $childRepository,
        string $mappedByPropertyName,
        EntityInterface|EntityCollection $parents,
        RepositoryInterface $parentRepository,
        ?string $orderBy = null,
        ?callable $apply = null,
    ): EntityCollection {
        $inverse = $childRepository->getRelation($mappedByPropertyName);
        if ($inverse === null) {
            throw new RuntimeException('Relation not found for mappedBy property: ' . $mappedByPropertyName);
        }

        if ($inverse instanceof BelongsTo || $inverse instanceof BelongsToOne) {
            $fkCol = $childRepository->getHydrator()->getColumnNameForProperty($inverse->foreignKey)
                ?? $inverse->foreignKey;
            $ids = self::getParentMatchValues($parents, $inverse, $parentRepository);
            if ($ids === []) {
                return new EntityCollection([], $childRepository);
            }

            $query = $childRepository->sqlQuery()
                ->whereIn($fkCol, $ids);
            if ($orderBy !== null) {
                $query->orderByRaw($orderBy);
            }
            if ($apply !== null) {
                $apply($query, $parents, $inverse, $childRepository, $parentRepository);
            }
            return $query->getResult();
        }

        if ($inverse instanceof MorphTo || $inverse instanceof MorphToOne) {
            $parentClass = $parentRepository->getHydrator()->getEntityClass();
            $disc = $inverse->discriminatorForClass($parentClass);
            $fkCol = $childRepository->getHydrator()->getColumnNameForProperty($inverse->foreignKey)
                ?? $inverse->foreignKey;
            $typeCol = $childRepository->getHydrator()->getColumnNameForProperty($inverse->typeColumn)
                ?? $inverse->typeColumn;

            if ($parents instanceof EntityInterface) {
                $pid = $parents->getId();
                if ($pid === null) {
                    return new EntityCollection([], $childRepository);
                }
                $fkValues = [$pid];
            } else {
                $fkValues = $parents->getIds();
                if ($fkValues === []) {
                    return new EntityCollection([], $childRepository);
                }
            }

            $query = $childRepository->sqlQuery()
                ->where($typeCol, $disc)
                ->whereIn($fkCol, $fkValues);
            if ($orderBy !== null) {
                $query->orderByRaw($orderBy);
            }
            if ($apply !== null) {
                $apply($query, $parents, $inverse, $childRepository, $parentRepository);
            }
            return $query->getResult();
        }

        throw new RuntimeException('Unsupported child relation for HasMany/HasOne mappedBy: ' . get_class($inverse));
    }
}
