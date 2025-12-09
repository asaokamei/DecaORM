<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * HasMany relationship attribute
 * 
 * Used on the side that does not hold the foreign key (the "one" side).
 * This is equivalent to OneToMany in Doctrine.
 * 
 * Example:
 * ```php
 * #[HasMany(targetEntity: Post::class, foreignKey: 'user_id')]
 * public ?array $posts = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany
{
    /** @var string The property name on the attribute's entity */
    public string $propertyName = '';

    /**
     * @param string $targetEntity The target entity class name (e.g., Post::class)
     * @param string $mappedBy The property name on the inverse side (e.g., 'user')
     * @param string|null $orderBy Optional ORDER BY clause (e.g., 'created_at DESC')
     * @param string $fetch Fetch strategy: 'LAZY' or 'EAGER' (default: 'LAZY')
     */
    public function __construct(
        public string $targetEntity,
        public string $mappedBy,
        public ?string $orderBy = null,
        public string $fetch = 'LAZY'
    ) {
    }
}

