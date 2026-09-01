<?php

namespace WScore\DecaORM\Attribute;

use Attribute;
use WScore\DecaORM\Contracts\EntityInterface;

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
class BelongsTo
{
    /** @var string The property name on the attribute's entity */
    public string $propertyName = '';

    public ?string $sourceFilter = null;
    public ?string $apply = null;
    public ?string $targetScope = null;

    /**
     * @param string $targetEntity The target entity class name (e.g., User::class)
     * @param string $foreignKey The foreign key property name (e.g., 'user_id')
     * @param string|null $ownerKey The target entity property name to match against (defaults to target primary key)
     * @param string|null $inversedBy The property name on the inverse side (for bidirectional relationships)
     * @param string|null $sourceFilter Optional method name in the source repository to modify the target query (Query hook).
     * @param string|null $targetScope Optional method name in the target repository to apply scope to the target query.
     * @param string|null $apply Deprecated alias for sourceFilter.
     */
    public function __construct(
        public string $targetEntity,
        public string $foreignKey,
        public ?string $ownerKey = null,
        public ?string $inversedBy = null,
        ?string $sourceFilter = null,
        ?string $targetScope = null,
        ?string $apply = null,
    ) {
        $this->sourceFilter = $sourceFilter ?? $apply;
        $this->apply = $this->sourceFilter;
        $this->targetScope = $targetScope;
    }

    /**
     * Sets the relation property and foreign key on the owning entity only.
     * Does not update inversedBy / inverse collections; that is handled at a higher level.
     */
    public function associate(EntityInterface $entity, ?EntityInterface $target): void
    {
        $entity->setRaw($this->propertyName, $target);
        $id = $target?->getId();
        $entity->setRaw($this->foreignKey, $id !== null ? (string) $id : null);
    }
}

