<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

/**
 * Common utilities for relation loading.
 */
trait RelationTrait
{
    /**
     * Collect entity IDs and create a map of ID => [entities with this ID].
     * Skips entities with null IDs.
     * 
     * @param array<EntityInterface> $entities
     * @return array{0: array<int|string>, 1: array<int|string, array<EntityInterface>>}
     *         Returns [ids, idMap] where ids is array of unique IDs and idMap is ID => [entities]
     */
    /**
     * Collect entity IDs and create a map of ID => entity.
     * Skips entities with null IDs.
     *
     * @param array<EntityInterface> $entities
     * @return array{0: array<int|string>, 1: array<int|string, EntityInterface>}
     *         Returns [ids, idMap] where ids is array of unique IDs and idMap is ID => entity
     */
    protected static function collectEntityIds(array $entities): array
    {
        $ids = [];
        $idMap = []; // id => entity

        foreach ($entities as $entity) {
            $id = $entity->getId();
            if ($id === null) {
                continue; // Skip entities without ID
            }
            if (!isset($idMap[$id])) {
                $ids[] = $id;
                $idMap[$id] = $entity;
            }
            // If duplicate IDs exist, keep the first encountered entity instance.
        }

        return [$ids, $idMap];
    }

    /**
     * Collect parent IDs from child entities using foreign key.
     * Skips entities with null foreign keys.
     * 
     * @param array<EntityInterface> $childEntities
     * @param string $foreignKey The foreign key property name
     * @return array{0: array<int|string>, 1: array<int|string, array<EntityInterface>>, 2: array<EntityInterface>}
     *         Returns [parentIds, childrenByParentId, childrenWithoutParent]
     */
    protected static function collectParentIdsFromChildren(
        array $childEntities,
        string $foreignKey
    ): array {
        $parentIds = [];
        $childrenByParentId = []; // parentId => [child entities with this parentId]
        $childrenWithoutParent = []; // child entities with null foreign key

        foreach ($childEntities as $childEntity) {
            $parentId = $childEntity->get($foreignKey);
            if ($parentId === null) {
                $childrenWithoutParent[] = $childEntity;
                continue;
            }
            if (!isset($childrenByParentId[$parentId])) {
                $childrenByParentId[$parentId] = [];
                $parentIds[] = $parentId;
            }
            $childrenByParentId[$parentId][] = $childEntity;
        }

        return [$parentIds, $childrenByParentId, $childrenWithoutParent];
    }

    /**
     * Group entities by a foreign key value.
     * 
     * @param array<EntityInterface> $entities
     * @param string $foreignKeyProperty The property name to use for grouping
     * @return array<int|string, array<EntityInterface>> Map of foreignKeyValue => [entities]
     */
    protected static function groupEntitiesByForeignKey(
        array $entities,
        string $foreignKeyProperty
    ): array {
        $grouped = [];
        foreach ($entities as $entity) {
            $foreignKeyValue = $entity->get($foreignKeyProperty);
            if ($foreignKeyValue !== null) {
                if (!isset($grouped[$foreignKeyValue])) {
                    $grouped[$foreignKeyValue] = [];
                }
                $grouped[$foreignKeyValue][] = $entity;
            }
        }
        return $grouped;
    }

    /**
     * Create a map of entity ID => entity from an array of entities.
     * 
     * @param array<EntityInterface> $entities
     * @return array<int|string, EntityInterface> Map of id => entity
     */
    protected static function createEntityMap(array $entities): array
    {
        $map = [];
        foreach ($entities as $entity) {
            $id = $entity->getId();
            if ($id !== null) {
                $map[$id] = $entity;
            }
        }
        return $map;
    }

    /**
     * @param HasMany|HasOne $parentRelation
     * @param RepositoryInterface|null $sourceRepository
     * @return array|null
     */
    public static function getLoader(HasMany|HasOne $parentRelation, ?RepositoryInterface $sourceRepository): ?array
    {
        $loader = null;
        // If loader is specified, use it instead of WHERE IN query
        if ($parentRelation->loader !== null) {
            if ($sourceRepository === null) {
                throw new RuntimeException(
                    'Source repository is required when using loader. ' .
                    'Please pass the source repository to LoadHasMany::load()'
                );
            }

            if (!method_exists($sourceRepository, $parentRelation->loader)) {
                throw new RuntimeException(
                    'Loader method "' . $parentRelation->loader . '" not found in repository: ' . $sourceRepository::class
                );
            }
            $loader = [$sourceRepository, $parentRelation->loader];
        }
        return $loader;
    }
}

