<?php

namespace WScore\DecaORM\Trait;

use PDO;
use RuntimeException;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Contracts\EntityInterface;

/**
 * Trait for repositories that need ManyToMany relationship synchronization.
 * 
 * Provides the syncManyToMany() method to synchronize many-to-many relationships.
 * 
 * Usage:
 * ```php
 * class StudentRepository extends AbstractRepository
 * {
 *     use ManyToManyTrait;
 *     // ...
 * }
 * 
 * $student->set('courses', [$course1, $course2]);
 * $studentRepo->syncManyToMany($student, 'courses');
 * ```
 */
trait ManyToManyTrait
{
    /**
     * Synchronize many-to-many relationship.
     * 
     * This method synchronizes the relationship to match the entities set on the relation property.
     * It reads target entities from the entity's relation property, extracts their IDs,
     * compares with the current state in the database, and performs INSERT/DELETE operations as needed.
     * 
     * Usage:
     * ```php
     * $student->set('courses', [$course1, $course2]);
     * $studentRepo->syncManyToMany($student, 'courses');
     * ```
     * 
     * @param EntityInterface $entity The entity to sync relations for
     * @param string $relationName The name of the relation property
     * @return void
     * @throws RuntimeException If the relation is not ManyToMany or entity has no ID
     */
    public function syncManyToMany(EntityInterface $entity, string $relationName): void
    {
        $relation = $this->hydrator->getRelation($relationName);
        
        if (!($relation instanceof ManyToMany)) {
            throw new RuntimeException(
                "Relation '{$relationName}' is not a ManyToMany relationship"
            );
        }

        $entityId = $entity->getId();
        if ($entityId === null) {
            throw new RuntimeException(
                "Entity must have an ID to sync relations"
            );
        }

        // Extract target IDs from the entity's relation property
        $targetIds = $this->extractTargetIdsFromRelation($entity, $relationName);

        // Get current related IDs from database
        $currentIds = $this->getCurrentRelatedIds($entityId, $relation);

        // Calculate differences
        $idsToAdd = array_diff($targetIds, $currentIds);
        $idsToRemove = array_diff($currentIds, $targetIds);

        // Perform INSERT for new relationships
        if (!empty($idsToAdd)) {
            $this->insertRelations($entityId, $idsToAdd, $relation);
        }

        // Perform DELETE for removed relationships
        if (!empty($idsToRemove)) {
            $this->deleteRelations($entityId, $idsToRemove, $relation);
        }
    }

    /**
     * Extract target entity IDs from the entity's relation property.
     * 
     * @param \WScore\DecaORM\Contracts\EntityInterface $entity
     * @param string $relationName
     * @return array<int|string>
     */
    private function extractTargetIdsFromRelation(EntityInterface $entity, string $relationName): array
    {
        $relationValue = $entity->get($relationName);
        
        // Handle null or empty
        if ($relationValue === null) {
            return [];
        }
        
        // Handle array of entities
        if (is_array($relationValue)) {
            $ids = [];
            foreach ($relationValue as $targetEntity) {
                if ($targetEntity instanceof EntityInterface) {
                    $id = $targetEntity->getId();
                    if ($id !== null) {
                        $ids[] = $id;
                    }
                }
            }
            return $ids;
        }
        
        // Handle single entity (shouldn't happen for ManyToMany, but handle gracefully)
        if ($relationValue instanceof EntityInterface) {
            $id = $relationValue->getId();
            return $id !== null ? [$id] : [];
        }
        
        // Invalid type - return empty array
        return [];
    }

    /**
     * Get current related IDs from the join table.
     * 
     * @param int|string $entityId
     * @param ManyToMany $relation
     * @return array<int|string>
     */
    private function getCurrentRelatedIds(int|string $entityId, ManyToMany $relation): array
    {
        $sql = "SELECT {$relation->inverseForeignKey} 
                FROM {$relation->joinTable} 
                WHERE {$relation->foreignKey} = :entity_id";

        $stmt = $this->execute($sql, ['entity_id' => $entityId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_column($rows, $relation->inverseForeignKey);
    }

    /**
     * Insert new relationships into the join table.
     * 
     * @param int|string $entityId
     * @param array<int|string> $targetIds
     * @param ManyToMany $relation
     * @return void
     */
    private function insertRelations(int|string $entityId, array $targetIds, ManyToMany $relation): void
    {
        if (empty($targetIds)) {
            return;
        }

        $sql = "INSERT INTO {$relation->joinTable} ({$relation->foreignKey}, {$relation->inverseForeignKey}) 
                VALUES ";

        $values = [];
        $params = [];
        foreach ($targetIds as $idx => $targetId) {
            $values[] = "(:entity_id, :target_id_{$idx})";
            $params["target_id_{$idx}"] = $targetId;
        }
        $sql .= implode(', ', $values);
        $params['entity_id'] = $entityId;

        $this->execute($sql, $params);
    }

    /**
     * Delete relationships from the join table.
     * 
     * @param int|string $entityId
     * @param array<int|string> $targetIds
     * @param ManyToMany $relation
     * @return void
     */
    private function deleteRelations(int|string $entityId, array $targetIds, ManyToMany $relation): void
    {
        if (empty($targetIds)) {
            return;
        }

        $placeholders = [];
        $params = ['entity_id' => $entityId];
        foreach ($targetIds as $idx => $targetId) {
            $placeholder = ":target_id_{$idx}";
            $placeholders[] = $placeholder;
            $params["target_id_{$idx}"] = $targetId;
        }

        $sql = "DELETE FROM {$relation->joinTable} 
                WHERE {$relation->foreignKey} = :entity_id 
                AND {$relation->inverseForeignKey} IN (" . implode(', ', $placeholders) . ")";

        $this->execute($sql, $params);
    }
}

