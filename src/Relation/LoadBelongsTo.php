<?php

namespace WScore\DecaORM\Relation;

use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

class LoadBelongsTo
{
    use RelationBelongsToTrait;

    private static function getSourceFilter(BelongsTo $relation, ?RepositoryInterface $sourceRepository): ?callable
    {
        $filter = $relation->sourceFilter ?? $relation->apply ?? null;
        if ($filter === null || $filter === '') {
            return null;
        }
        if ($sourceRepository === null) {
            throw new \RuntimeException('Source repository is required when using sourceFilter/apply for BelongsTo.');
        }
        if (!method_exists($sourceRepository, $filter)) {
            throw new \RuntimeException(
                'Source filter method "' . $filter . '" not found in repository: ' . $sourceRepository::class
            );
        }
        $callable = [$sourceRepository, $filter];

        return function (
            \WScore\DecaORM\Sql\Query $query,
            EntityInterface|EntityCollection $children,
            BelongsTo $rel,
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

    private static function getApply(BelongsTo $relation, ?RepositoryInterface $sourceRepository): ?callable
    {
        return self::getSourceFilter($relation, $sourceRepository);
    }

    private static function getTargetScope(BelongsTo $relation, RepositoryInterface $targetRepository): ?callable
    {
        $scope = $relation->targetScope ?? null;
        if ($scope === null || $scope === '') {
            return null;
        }
        if (!method_exists($targetRepository, $scope)) {
            throw new \RuntimeException(
                'Target scope method "' . $scope . '" not found in repository: ' . $targetRepository::class
            );
        }
        $callable = [$targetRepository, $scope];

        return function (
            \WScore\DecaORM\Sql\Query $query,
            EntityInterface|EntityCollection $children,
            BelongsTo $rel,
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
     * Load BelongsTo relation for single entity or multiple entities.
     * 
     * @param EntityInterface|EntityCollection<EntityInterface> $entities
     * @param BelongsTo $childRelation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository
     * @param \WScore\DecaORM\Contracts\RepositoryInterface|null $sourceRepository The repository for the source entities (needed for sourceFilter/apply)
     * @return EntityInterface[] All loaded parent entities (array with 0 or 1 element per child)
     */
    public static function load(
        EntityInterface|EntityCollection $entities,
        BelongsTo $childRelation,
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
     * Load BelongsTo relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $childEntity,
        BelongsTo $childRelation,
        RepositoryInterface $targetRepository,
        ?callable $sourceFilter = null,
        ?RepositoryInterface $sourceRepository = null,
        ?callable $targetScope = null,
    ): array {

        $parentEntity = self::loadSingleEntity($childEntity, $childRelation, $targetRepository, $sourceFilter, $sourceRepository, $targetScope);
        // Note: BelongsTo does not set child on parent (parent may have HasMany, which is dangerous)
        return $parentEntity ? [$parentEntity]: [];
    }

    /**
     * Batch load BelongsTo relations for multiple entities.
     * 
     * @param EntityCollection<EntityInterface> $childEntities
     * @param BelongsTo $childRelation
     * @param RepositoryInterface $targetRepository
     * @param callable|null $sourceFilter
     * @param RepositoryInterface|null $sourceRepository
     * @param callable|null $targetScope
     * @return \WScore\DecaORM\Contracts\EntityInterface[] All loaded parent entities (array with 0 or 1 element per child)
     */
    public static function loadBatch(
        EntityCollection $childEntities,
        BelongsTo $childRelation,
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
            foreach ($children as $childEntity) {
                $childEntity->setRaw($childRelation->propertyName, null);
            }
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

        return array_unique($allParents, SORT_REGULAR);
    }

}