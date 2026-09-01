<?php

namespace WScore\DecaORM\Relation;

use PDO;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\Sql\Query;

class LoadManyToMany
{
    use RelationTrait;
    /**
     * Load ManyToMany relation for single entity or multiple entities.
     * 
     * @param EntityInterface|EntityCollection<EntityInterface> $entities
     * @param ManyToMany $relation
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $sourceRepository The repository for the source entities
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository The repository for the target entities
     * @return \WScore\DecaORM\Contracts\EntityInterface[] All loaded target entities
     */
    public static function load(
        EntityInterface|EntityCollection $entities,
        ManyToMany $relation,
        RepositoryInterface $sourceRepository,
        RepositoryInterface $targetRepository
    ): array {
        if ($entities instanceof EntityInterface) {
            return self::loadSingle($entities, $relation, $sourceRepository, $targetRepository);
        }
        if (count($entities) === 0) {
            return [];
        }
        if (count($entities) === 1) {
            $first = $entities->first();
            if (!$first instanceof EntityInterface) {
                return [];
            }
            return self::loadSingle($first, $relation, $sourceRepository, $targetRepository);
        }
        return self::loadBatch($entities, $relation, $sourceRepository, $targetRepository);
    }

    /**
     * Load ManyToMany relation for a single entity.
     */
    private static function loadSingle(
        EntityInterface $entity,
        ManyToMany $relation,
        RepositoryInterface $sourceRepository,
        RepositoryInterface $targetRepository
    ): array {
        $propertyName = $relation->propertyName;
        $entityId = $entity->getId();
        
        if ($entityId === null) {
            $entity->setRaw($propertyName, new EntityCollection([], $targetRepository));
            return [];
        }

        // Get related IDs from join table
        $relatedIds = self::getRelatedIdsFromJoinTable(
            $sourceRepository,
            $relation,
            $entityId
        );

        if (empty($relatedIds)) {
            $entity->setRaw($propertyName, new EntityCollection([], $targetRepository));
            return [];
        }

        $targetEntities = self::fetchTargetEntities(
            $relatedIds,
            $relation,
            $entity,
            $sourceRepository,
            $targetRepository
        );

        // Note: We do NOT set bidirectional links for ManyToMany relations.
        // Unlike HasOne/BelongsToOne (1:1), ManyToMany relations may have multiple
        // related entities on both sides. Setting a partial list on the inverse side
        // would be misleading, as it would not represent the complete relationship.
        // Users should explicitly call load() on the inverse side if needed.

        $entity->setRaw($propertyName, $targetEntities);
        return $targetEntities->getEntities();
    }

    /**
     * Batch load ManyToMany relations for multiple entities.
     * 
     * @param EntityCollection<EntityInterface> $entities
     * @param ManyToMany $relation
     * @param RepositoryInterface $sourceRepository The repository for the source entities
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $targetRepository The repository for the target entities
     * @return \WScore\DecaORM\Contracts\EntityInterface[] All loaded target entities
     */
    public static function loadBatch(
        EntityCollection $entities,
        ManyToMany $relation,
        RepositoryInterface $sourceRepository,
        RepositoryInterface $targetRepository
    ): array {
        if (count($entities) === 0) {
            return [];
        }

        $propertyName = $relation->propertyName;

        $sourceColl = $entities;
        $entityMap = $sourceColl->getIdMap();
        $entityIds = array_keys($entityMap);

        if (empty($entityIds)) {
            // Set empty collections for all entities
            foreach ($sourceColl as $entity) {
                $entity->setRaw($propertyName, new EntityCollection([], $targetRepository));
            }
            return [];
        }

        // Get all related IDs from join table for all entities
        $allRelatedIds = self::getRelatedIdsFromJoinTableBatch(
            $sourceRepository,
            $relation,
            $entityIds
        );

        if (empty($allRelatedIds)) {
            // Set empty collections for all entities
            foreach ($sourceColl as $entity) {
                $entity->setRaw($propertyName, new EntityCollection([], $targetRepository));
            }
            return [];
        }

        $uniqueRelatedIds = array_unique($allRelatedIds);
        $targetEntities = self::fetchTargetEntities(
            $uniqueRelatedIds,
            $relation,
            $sourceColl,
            $sourceRepository,
            $targetRepository
        );

        $targetEntityMap = $targetEntities->getIdMap();

        // Group target entities by source entity ID
        $relatedIdsByEntityId = self::groupRelatedIdsByEntityId(
            $sourceRepository,
            $relation,
            $entityIds
        );

        // Set target entities for each source entity
        $allTargetEntities = [];
        foreach ($entityMap as $entityId => $entity) {
            $relatedIds = $relatedIdsByEntityId[$entityId] ?? [];
            $targetsForEntity = [];
            foreach ($relatedIds as $relatedId) {
                if (isset($targetEntityMap[$relatedId])) {
                    $targetsForEntity[] = $targetEntityMap[$relatedId];
                }
            }

            // Note: We do NOT set bidirectional links for ManyToMany relations.
            // Unlike HasOne/BelongsToOne (1:1), ManyToMany relations may have multiple
            // related entities on both sides. Setting a partial list on the inverse side
            // would be misleading, as it would not represent the complete relationship.
            // Users should explicitly call load() on the inverse side if needed.

            // Set targets for all entities with this ID
            $entity->setRaw($propertyName, new EntityCollection($targetsForEntity, $targetRepository));

            $allTargetEntities = array_merge($allTargetEntities, $targetsForEntity);
        }

        // Set empty collections for entities that had no related entities
        foreach ($sourceColl as $entity) {
            if ($entity->getRaw($propertyName) === null) {
                $entity->setRaw($propertyName, new EntityCollection([], $targetRepository));
            }
        }

        return array_values((new EntityCollection($allTargetEntities, $targetRepository))->getIdMap());
    }

    /**
     * Get related IDs from join table for a single entity.
     * 
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $sourceRepository
     * @param ManyToMany $relation
     * @param int|string $entityId
     * @return array<int|string>
     */
    private static function getRelatedIdsFromJoinTable(
        RepositoryInterface $sourceRepository,
        ManyToMany $relation,
        int|string $entityId
    ): array {
        $query = self::newJoinTableQuery($sourceRepository, $relation)
            ->where($relation->foreignKey, $entityId);

        $stmt = $query->getPdoStatement();
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_column($rows, $relation->inverseForeignKey);
    }

    /**
     * Get all related IDs from join table for multiple entities.
     * 
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $sourceRepository
     * @param ManyToMany $relation
     * @param array<int|string> $entityIds
     * @return array<int|string> All related IDs (may contain duplicates)
     */
    private static function getRelatedIdsFromJoinTableBatch(
        RepositoryInterface $sourceRepository,
        ManyToMany $relation,
        array $entityIds
    ): array {
        if (empty($entityIds)) {
            return [];
        }

        $query = self::newJoinTableQuery($sourceRepository, $relation)
            ->select($relation->foreignKey, $relation->inverseForeignKey)
            ->whereIn($relation->foreignKey, $entityIds);

        $stmt = $query->getPdoStatement();
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_column($rows, $relation->inverseForeignKey);
    }

    /**
     * Group related IDs by entity ID.
     * 
     * @param \WScore\DecaORM\Contracts\RepositoryInterface $sourceRepository
     * @param ManyToMany $relation
     * @param array<int|string> $entityIds
     * @return array<int|string, array<int|string>> Map of entityId => [relatedIds]
     */
    private static function groupRelatedIdsByEntityId(
        RepositoryInterface $sourceRepository,
        ManyToMany $relation,
        array $entityIds
    ): array {
        if (empty($entityIds)) {
            return [];
        }

        $query = self::newJoinTableQuery($sourceRepository, $relation)
            ->select($relation->foreignKey, $relation->inverseForeignKey)
            ->whereIn($relation->foreignKey, $entityIds);

        $stmt = $query->getPdoStatement();
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $entityId = $row[$relation->foreignKey];
            $relatedId = $row[$relation->inverseForeignKey];
            if (!isset($grouped[$entityId])) {
                $grouped[$entityId] = [];
            }
            $grouped[$entityId][] = $relatedId;
        }

        return $grouped;
    }

    /**
     * Build a {@see Query} for the ManyToMany join (pivot) table via the source repository.
     */
    private static function newJoinTableQuery(RepositoryInterface $sourceRepository, ManyToMany $relation): Query
    {
        $query = $sourceRepository->sqlQuery()
            ->from($relation->joinTable)
            ->select($relation->inverseForeignKey);

        if ($relation->orderBy !== null) {
            $query->orderBy($relation->orderBy);
        }

        return $query;
    }

    /**
     * @param array<int|string> $relatedIds
     */
    private static function fetchTargetEntities(
        array $relatedIds,
        ManyToMany $relation,
        EntityInterface|EntityCollection $sourceEntities,
        RepositoryInterface $sourceRepository,
        RepositoryInterface $targetRepository
    ): EntityCollection {
        $pkCol = $targetRepository->getHydrator()->getPrimaryKeyColumn();
        $query = $targetRepository->sqlQuery()->whereIn($pkCol, $relatedIds);
        if ($relation->orderBy !== null) {
            $query->orderBy($relation->orderBy);
        }

        $targetScope = self::resolveTargetScope($relation, $targetRepository);
        if ($targetScope !== null) {
            $targetScope($query, $sourceEntities, $relation, $targetRepository, $sourceRepository);
        }

        $sourceFilter = self::resolveSourceFilter($relation, $sourceRepository);
        if ($sourceFilter !== null) {
            $sourceFilter($query, $sourceEntities, $relation, $targetRepository, $sourceRepository);
        }

        return $query->getResult();
    }
}

