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

    public ?string $sourceFilter = null;
    public ?string $apply = null;
    public ?string $targetScope = null;

    /**
     * @param string $targetEntity The target entity class name
     * @param string $mappedBy The property name on the inverse side (e.g., 'user')
     * @param string|null $sourceFilter Optional method name in the source repository to modify the target query (Query hook).
     * @param string|null $targetScope Optional method name in the target repository to apply scope to the target query.
     * @param string|null $apply Deprecated alias for sourceFilter.
     */
    public function __construct(
        public string $targetEntity,
        public string $mappedBy,
        ?string $sourceFilter = null,
        ?string $targetScope = null,
        ?string $apply = null
    ) {
        $this->sourceFilter = $sourceFilter ?? $apply;
        $this->apply = $this->sourceFilter;
        $this->targetScope = $targetScope;
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

