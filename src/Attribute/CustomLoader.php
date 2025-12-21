<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * CustomLoader relationship attribute
 * 
 * Used for complex relations that cannot be handled by standard relation attributes.
 * Examples include composite keys, complex conditions, or cases where mappedBy cannot be determined.
 * 
 * The loader method should handle both fetching and mapping entities.
 * 
 * Example:
 * ```php
 * #[CustomLoader(targetEntity: Task::class, method: 'findTasks')]
 * public array $tasks;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class CustomLoader
{
    /** @var string The property name on the attribute's entity */
    public string $propertyName = '';

    /**
     * @param string $targetEntity The target entity class name (e.g., Task::class)
     * @param string $method The method name in the repository to load this relation
     */
    public function __construct(
        public string $targetEntity,
        public string $method
    ) {
    }
}

