<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\CustomLoader;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;

/**
 * Handles CustomLoader relations.
 * 
 * CustomLoader is used for complex relations that cannot be handled by standard relation attributes.
 * The loader method should handle both fetching and mapping entities.
 */
class LoadCustomLoader
{
    /**
     * Load CustomLoader relation for single entity or multiple entities.
     * 
     * @param EntityInterface|array<\WScore\DecaORM\Contracts\EntityInterface> $entities
     * @param CustomLoader $relation
     * @param RepositoryInterface $repository The repository that contains the loader method
     * @return EntityInterface[] Loaded relation entities (may be empty if mapping is done in loader)
     */
    public static function load(
        EntityInterface|array $entities,
        CustomLoader $relation,
        RepositoryInterface $repository
    ): array {
        if (!method_exists($repository, $relation->method)) {
            throw new RuntimeException(
                'Loader method "' . $relation->method . '" not found in repository: ' . $repository::class
            );
        }

        // Call the loader method
        // The loader method should handle both fetching and mapping entities
        // It can return EntityInterface[] or void (if mapping is done internally)
        $result = $repository->{$relation->method}($entities);

        // If result is EntityInterface[], return it
        // If result is void or null, return empty array (mapping was done in loader)
        if (is_array($result)) {
            return $result;
        }

        return [];
    }
}

