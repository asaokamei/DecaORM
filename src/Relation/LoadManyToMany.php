<?php

namespace WScore\DecaORM\Relation;

use PDO;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Contacts\EntityInterface;
use WScore\DecaORM\Contacts\RepositoryInterface;
use WScore\DecaORM\Sql\QueryBuilder;

class LoadManyToMany
{
    use RelationTrait;

    /**
     * Load ManyToMany relation for single entity or multiple entities.
     * 
     * @param EntityInterface|array<\WScore\DecaORM\Contacts\EntityInterface> $entities
     * @param ManyToMany $relation
     * @param \WScore\DecaORM\Contacts\RepositoryInterface $sourceRepository The repository for the source entities
     * @param \WScore\DecaORM\Contacts\RepositoryInterface $targetRepository The repository for the target entities
     * @return \WScore\DecaORM\Contacts\EntityInterface[] All loaded target entities
     */
    public static function load(
        EntityInterface|array $entities,
        ManyToMany $relation,
        RepositoryInterface $sourceRepository,
        RepositoryInterface $targetRepository
    ): array {
        if (is_array($entities)) {
            return self::loadBatch($entities, $relation, $sourceRepository, $targetRepository);
        }
        
        // Single entity
        return self::loadSingle($entities, $relation, $sourceRepository, $targetRepository);
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
            $entity->set($propertyName, []);
            return [];
        }

        // Get related IDs from join table
        $relatedIds = self::getRelatedIdsFromJoinTable(
            $sourceRepository,
            $relation,
            $entityId
        );

        if (empty($relatedIds)) {
            $entity->set($propertyName, []);
            return [];
        }

        // Load target entities
        $query = $targetRepository->sqlQuery()
            ->whereIn($targetRepository->getHydrator()->getPrimaryKeyColumn(), $relatedIds);
        if ($relation->orderBy !== null) {
            $query->orderBy($relation->orderBy);
        }
        $targetEntities = $query->getResult();

        // Note: We do NOT set bidirectional links for ManyToMany relations.
        // Unlike HasOne/BelongsToOne (1:1), ManyToMany relations may have multiple
        // related entities on both sides. Setting a partial list on the inverse side
        // would be misleading, as it would not represent the complete relationship.
        // Users should explicitly call load() on the inverse side if needed.

        $entity->set($propertyName, $targetEntities);
        return $targetEntities;
    }

    /**
     * Batch load ManyToMany relations for multiple entities.
     * 
     * @param array<\WScore\DecaORM\Contacts\EntityInterface> $entities
     * @param ManyToMany $relation
     * @param RepositoryInterface $sourceRepository The repository for the source entities
     * @param \WScore\DecaORM\Contacts\RepositoryInterface $targetRepository The repository for the target entities
     * @return \WScore\DecaORM\Contacts\EntityInterface[] All loaded target entities
     */
    public static function loadBatch(
        array $entities,
        ManyToMany $relation,
        RepositoryInterface $sourceRepository,
        RepositoryInterface $targetRepository
    ): array {
        if (empty($entities)) {
            return [];
        }

        $propertyName = $relation->propertyName;

        // Collect entity IDs (skip null IDs)
        [$entityIds, $entityMap] = self::collectEntityIds($entities);

        if (empty($entityIds)) {
            // Set empty arrays for all entities
            foreach ($entities as $entity) {
                $entity->set($propertyName, []);
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
            // Set empty arrays for all entities
            foreach ($entities as $entity) {
                $entity->set($propertyName, []);
            }
            return [];
        }

        // Load all target entities
        $uniqueRelatedIds = array_unique($allRelatedIds);
        $query = $targetRepository->sqlQuery()
            ->whereIn($targetRepository->getHydrator()->getPrimaryKeyColumn(), $uniqueRelatedIds);
        if ($relation->orderBy !== null) {
            $query->orderBy($relation->orderBy);
        }
        $targetEntities = $query->getResult();

        // Create a map of target entity ID => target entity
        $targetEntityMap = self::createEntityMap($targetEntities);

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
            $entity->set($propertyName, $targetsForEntity);

            $allTargetEntities = array_merge($allTargetEntities, $targetsForEntity);
        }

        // Set empty arrays for entities that had no related entities
        if (!$entity->get($propertyName)) {
            $entity->set($propertyName, []);
        }

        // Remove duplicates based on entity ID
        $uniqueTargetEntities = self::createEntityMap($allTargetEntities);
        return array_values($uniqueTargetEntities);
    }

    /**
     * Get related IDs from join table for a single entity.
     * 
     * @param \WScore\DecaORM\Contacts\RepositoryInterface $sourceRepository
     * @param ManyToMany $relation
     * @param int|string $entityId
     * @return array<int|string>
     */
    private static function getRelatedIdsFromJoinTable(
        RepositoryInterface $sourceRepository,
        ManyToMany $relation,
        int|string $entityId
    ): array {
        $query = (new QueryBuilder())
            ->select($relation->inverseForeignKey)
            ->from($relation->joinTable)
            ->where($relation->foreignKey, $entityId);
        
        if ($relation->orderBy !== null) {
            $query->orderBy($relation->orderBy);
        }

        $sql = $query->getSql();
        $params = $query->getParameters();

        $stmt = $sourceRepository->execute($sql, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_column($rows, $relation->inverseForeignKey);
    }

    /**
     * Get all related IDs from join table for multiple entities.
     * 
     * @param \WScore\DecaORM\Contacts\RepositoryInterface $sourceRepository
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

        $query = (new QueryBuilder())
            ->select($relation->foreignKey, $relation->inverseForeignKey)
            ->from($relation->joinTable)
            ->whereIn($relation->foreignKey, $entityIds);
        
        if ($relation->orderBy !== null) {
            $query->orderBy($relation->orderBy);
        }

        $sql = $query->getSql();
        $params = $query->getParameters();

        $stmt = $sourceRepository->execute($sql, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_column($rows, $relation->inverseForeignKey);
    }

    /**
     * Group related IDs by entity ID.
     * 
     * @param \WScore\DecaORM\Contacts\RepositoryInterface $sourceRepository
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

        $query = (new QueryBuilder())
            ->select($relation->foreignKey, $relation->inverseForeignKey)
            ->from($relation->joinTable)
            ->whereIn($relation->foreignKey, $entityIds);
        
        if ($relation->orderBy !== null) {
            $query->orderBy($relation->orderBy);
        }

        $sql = $query->getSql();
        $params = $query->getParameters();

        $stmt = $sourceRepository->execute($sql, $params);
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
}

