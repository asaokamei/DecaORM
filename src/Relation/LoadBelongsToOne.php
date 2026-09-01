<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

class LoadBelongsToOne
{
    use RelationBelongsToTrait;

    private static function getSourceFilter(BelongsToOne $relation, ?RepositoryInterface $sourceRepository): ?callable
    {
        $filter = $relation->sourceFilter ?? $relation->apply ?? null;
        if ($filter === null || $filter === '') {
            return null;
        }
        if ($sourceRepository === null) {
            throw new RuntimeException('Source repository is required when using sourceFilter/apply for BelongsToOne.');
        }
        if (!method_exists($sourceRepository, $filter)) {
            throw new RuntimeException(
                'Source filter method "' . $filter . '" not found in repository: ' . $sourceRepository::class
            );
        }
        $callable = [$sourceRepository, $filter];

        return function (
            \WScore\DecaORM\Sql\Query $query,
            EntityInterface|EntityCollection $children,
            BelongsToOne $rel,
            RepositoryInterface $targetRepo,
            ?RepositoryInterface $srcRepo
        ) use ($callable): void {
            $method = $callable[1] ?? null;
            $argc = is_string($method) && method_exists($callable[0], $method)
                ? (new \ReflectionMethod($callable[0], $method))->getNumberOfParameters()
                : 2;
            if ($argc <= 2) {
                ($callable)($query, $children);
                return;
            }
            ($callable)($query, $children, $rel, $targetRepo, $srcRepo);
        };
    }

    private static function getApply(BelongsToOne $relation, ?RepositoryInterface $sourceRepository): ?callable
    {
        return self::getSourceFilter($relation, $sourceRepository);
    }

    private static function getTargetScope(BelongsToOne $relation, RepositoryInterface $targetRepository): ?callable
    {
        $scope = $relation->targetScope ?? null;
        if ($scope === null || $scope === '') {
            return null;
        }
        if (!method_exists($targetRepository, $scope)) {
            throw new RuntimeException(
                'Target scope method "' . $scope . '" not found in repository: ' . $targetRepository::class
            );
        }
        $callable = [$targetRepository, $scope];

        return function (
            \WScore\DecaORM\Sql\Query $query,
            EntityInterface|EntityCollection $children,
            BelongsToOne $rel,
            RepositoryInterface $targetRepo,
            ?RepositoryInterface $srcRepo
        ) use ($callable): void {
            $method = $callable[1] ?? null;
            $argc = is_string($method) && method_exists($callable[0], $method)
                ? (new \ReflectionMethod($callable[0], $method))->getNumberOfParameters()
                : 1;
            if ($argc <= 1) {
                ($callable)($query);
                return;
            }
            if ($argc === 2) {
                ($callable)($query, $children);
                return;
            }
            ($callable)($query, $children, $rel, $targetRepo, $srcRepo);
        };
    }

    /**
     * Load BelongsToOne relation for a single entity or multiple entities.
     * 
     * @param EntityInterface|EntityCollection<EntityInterface> $entities
     * @param BelongsToOne $childRelation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @param \WScore\DecaORM\Contracts\RepositoryInterface|null $sourceRepository The repository for the source entities (needed for sourceFilter/apply)
     * @return EntityInterface[] All loaded parent entities (array with 0 or 1 elements per child)
     */
    public static function load(
        EntityInterface|EntityCollection $entities,
        BelongsToOne $childRelation,
        RepositoryInterface $targetRepository,
        ?RepositoryInterface $sourceRepository = null
    ): array {
        $sourceFilter = self::getSourceFilter($childRelation, $sourceRepository);
        $targetScope = self::getTargetScope($childRelation, $targetRepository);
        if ($entities instanceof EntityInterface) {
            return self::loadSingle($entities, $childRelation, $targetRepository, $sourceFilter, $sourceRepository, $targetScope);
        }
        if (count($entities) === 0) {
            return [];
        }
        if (count($entities) === 1) {
            $first = $entities->first();
            if (!$first instanceof EntityInterface) {
                return [];
            }
            return self::loadSingle($first, $childRelation, $targetRepository, $sourceFilter, $sourceRepository, $targetScope);
        }
        return self::loadBatch($entities, $childRelation, $targetRepository, $sourceFilter, $sourceRepository, $targetScope);
    }

    /**
     * Load BelongsToOne relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $childEntity,
        BelongsToOne $childRelation,
        RepositoryInterface $targetRepository,
        ?callable $sourceFilter = null,
        ?RepositoryInterface $sourceRepository = null,
        ?callable $targetScope = null,
    ): array {
        $parentEntity = self::loadSingleEntity($childEntity, $childRelation, $targetRepository, $sourceFilter, $sourceRepository, $targetScope);

        // Set child on parent if inversedBy is specified and parent has HasOne
        if ($parentEntity && $childRelation->inversedBy !== null) {
            self::setChildOnParent($parentEntity, $childRelation->inversedBy, $childEntity, $targetRepository);
        }

        return $parentEntity ? [$parentEntity]: [];
    }

    /**
     * Batch load BelongsToOne relations for multiple entities.
     * 
     * @param EntityCollection<EntityInterface> $childEntities
     * @param BelongsToOne $childRelation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @param callable|null $sourceFilter
     * @param RepositoryInterface|null $sourceRepository
     * @param callable|null $targetScope
     * @return EntityInterface[] All loaded parent entities (array with 0 or 1 elements per child)
     */
    public static function loadBatch(
        EntityCollection $childEntities,
        BelongsToOne $childRelation,
        RepositoryInterface $targetRepository,
        ?callable $sourceFilter = null,
        ?RepositoryInterface $sourceRepository = null,
        ?callable $targetScope = null,
    ): array {
        if (count($childEntities) === 0) {
            return [];
        }

        $children = $childEntities;
        $matchValues = array_filter($children->getValues($childRelation->foreignKey));
        if (empty($matchValues)) {
            return [];
        }

        $ownerKeyCol = self::resolveOwnerKeyColumn($childRelation, $targetRepository);
        $query = $targetRepository->sqlQuery()
            ->whereIn($ownerKeyCol, $matchValues);
        if ($targetScope !== null) {
            $targetScope($query, $children, $childRelation, $targetRepository, $sourceRepository);
        }
        if ($sourceFilter !== null) {
            $sourceFilter($query, $children, $childRelation, $targetRepository, $sourceRepository);
        }
        $parents = $query->getResult();

        $allParents = self::getParents($parents, $childRelation, $children, $targetRepository);

        // Set child on parent if inversedBy is specified and parent has HasOne
        if ($childRelation->inversedBy === null) {
            return $allParents;
        }
        $childrenByMatch = $children->groupBy($childRelation->foreignKey);
        foreach ($childrenByMatch as $matchValue => $child) {
            // For ownerKey != PK, we cannot use parents->hasId/findById.
            // Use already-linked child->propertyName when available.
            $parent = null;
            if (count($child) === 1) {
                $parent = $child[0]->getRaw($childRelation->propertyName);
            }
            if ($parent instanceof EntityInterface) {
                if (count($child) > 1) {
                    throw new RuntimeException('BelongsToOne relation can only have one child per parent, but multiple children found for match value: ' . $matchValue);
                }
                self::setChildOnParent($parent, $childRelation->inversedBy, $child[0], $targetRepository);
            }
        }

        return $allParents;
    }

    /**
     * Set child entity on parent entity if parent has HasOne relation.
     *
     * - If parent has HasMany: Do not set (dangerous - assumes all children are loaded)
     * - If parent has HasOne: Set as single entity (not array)
     */
    private static function setChildOnParent(
        EntityInterface $parentEntity,
        string $parentPropertyName,
        EntityInterface $childEntity,
        RepositoryInterface $targetRepository
    ): void {
        $parentRelation = $targetRepository->getRelation($parentPropertyName);

        if ($parentRelation instanceof HasOne) {
            // Set as single entity (not array)
            $parentEntity->setRaw($parentPropertyName, $childEntity);
        }
    }
}