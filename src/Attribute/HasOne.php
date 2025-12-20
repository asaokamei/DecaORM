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
    /** @var string The property name on the attribute's entity */
    public string $propertyName = '';
    /**
     * @param string $targetEntity The target entity class name
     * @param string $mappedBy The property name on the inverse side (e.g., 'user')
     */
    public function __construct(
        public string $targetEntity,
        public string $mappedBy
    ) {
    }
}

