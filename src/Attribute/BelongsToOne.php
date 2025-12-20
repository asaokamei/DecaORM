<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * BelongsTo relationship attribute
 * 
 * Used on the side that holds the foreign key (the "many" side).
 * This is equivalent to ManyToOne in Doctrine.
 * 
 * Example:
 * ```php
 * #[BelongsTo(targetEntity: User::class, foreignKey: 'user_id')]
 * public ?User $user = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsToOne
{
    /** @var string The property name on the attribute's entity */
    public string $propertyName = '';

    /**
     * @param string $targetEntity The target entity class name (e.g., User::class)
     * @param string $foreignKey The foreign key column name (e.g., 'user_id')
     * @param string|null $inversedBy The property name on the inverse side (for bidirectional relationships)
     */
    public function __construct(
        public string $targetEntity,
        public string $foreignKey,
        public ?string $inversedBy = null
    ) {
    }
}

