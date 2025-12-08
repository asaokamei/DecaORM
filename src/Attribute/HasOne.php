<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

/**
 * HasOne relationship attribute
 * 
 * Used for one-to-one relationships.
 * Can be used on either side, but typically on the side without the foreign key.
 * 
 * Example (with foreign key on target):
 * ```php
 * #[HasOne(targetEntity: Profile::class, foreignKey: 'user_id')]
 * public ?Profile $profile = null;
 * ```
 * 
 * Example (with foreign key on this side):
 * ```php
 * #[HasOne(targetEntity: User::class, foreignKey: 'profile_id', onThisSide: true)]
 * public ?User $user = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasOne
{
    /**
     * @param string $targetEntity The target entity class name
     * @param string $foreignKey The foreign key column name
     * @param bool $onThisSide Whether the foreign key is on this side (default: false, meaning on target side)
     * @param string|null $inversedBy The property name on the inverse side (for bidirectional relationships)
     * @param string $fetch Fetch strategy: 'LAZY' or 'EAGER' (default: 'LAZY')
     */
    public function __construct(
        public string $targetEntity,
        public string $foreignKey,
        public bool $onThisSide = false,
        public ?string $inversedBy = null,
        public string $fetch = 'LAZY'
    ) {
    }
}

