<?php

namespace WScore\DecaORM\Attribute;

use Attribute;
use WScore\DecaORM\Contracts\EntityInterface;

/**
 * HasOne relationship attribute
 * 
 * Used for one-to-one relationships.
 * Can be used on either side, but typically on the side without the foreign key.
 * 
 * Example:
 * ```php
 * // HasOne uses mappedBy (the BelongsTo/BelongsToOne property name on the target entity).
 * #[HasOne(targetEntity: Profile::class, mappedBy: 'user')]
 * public ?Profile $profile = null;
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
     * @param string|null $loader Optional method name in the repository to load this relation (for complex queries)
     */
    public function __construct(
        public string $targetEntity,
        public string $mappedBy,
        public ?string $loader = null
    ) {
    }

    /**
     * Sets the relation property on the owning entity only.
     * Does not update the target's mappedBy BelongsTo/BelongsToOne; that is handled at a higher level.
     */
    public function associate(EntityInterface $entity, ?EntityInterface $target): void
    {
        $entity->setRaw($this->propertyName, $target);
    }
}

