<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * CustomLoader relationship attribute
 * 
 * Used for complex relations that cannot be handled by standard relation attributes.
 * Examples include composite keys, complex conditions, or cases where mappedBy cannot be determined.
 * Can also be used for computed values (e.g., counts, aggregates).
 * 
 * The loader method should handle both fetching and mapping entities.
 * - For relations: return EntityInterface[] or void (if mapping is done internally)
 * - For computed values: return void and set values directly on entities
 * 
 * Example (relation):
 * ```php
 * #[CustomLoader(targetEntity: Task::class, method: 'findTasks')]
 * public array $tasks;
 * ```
 * 
 * Example (computed value):
 * ```php
 * #[CustomLoader(method: 'loadPostCount')]
 * public int $postCount = 0;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class CustomLoader
{
    /** @var string The property name on the attribute's entity */
    public string $propertyName = '';

    /**
     * @param string|null $targetEntity The target entity class name (e.g., Task::class). Optional for computed values.
     * @param string $method The method name in the repository to load this relation
     */
    public function __construct(
        public string $method,
        public ?string $targetEntity = null,
    ) {
    }
}

