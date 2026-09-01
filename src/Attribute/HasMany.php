<?php

namespace WScore\DecaORM\Attribute;

use Attribute;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\EntityCollection;

/**
 * HasMany relationship attribute
 * 
 * Used on the side that does not hold the foreign key (the "one" side).
 * This is equivalent to OneToMany in Doctrine.
 * 
 * Example:
 * ```php
 * // HasMany does not declare a foreignKey; it uses mappedBy.
 * // mappedBy is the BelongsTo property name on the target entity.
 * #[HasMany(targetEntity: Post::class, mappedBy: 'user')]
 * public ?array $posts = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany
{
    /** @var string The property name on the attribute's entity */
    public string $propertyName = '';

    public ?string $sourceFilter = null;
    public ?string $apply = null;
    public ?string $targetScope = null;

    /**
     * @param string $targetEntity The target entity class name (e.g., Post::class)
     * @param string $mappedBy The property name on the inverse side (e.g., 'user')
     * @param string|null $orderBy Optional ORDER BY clause (e.g., 'created_at DESC')
     * @param string|null $sourceFilter Optional method name in the source repository.
     *        Signature: `(Query $query, EntityInterface|EntityCollection $source): void`
     * @param string|null $targetScope Optional method name in the target repository.
     *        Signature: `(Query $query, EntityInterface|EntityCollection $source): void`
     * @param string|null $apply Deprecated alias for sourceFilter.
     */
    public function __construct(
        public string $targetEntity,
        public string $mappedBy,
        public ?string $orderBy = null,
        ?string $sourceFilter = null,
        ?string $targetScope = null,
        ?string $apply = null
    ) {
        $this->sourceFilter = $sourceFilter ?? $apply;
        $this->apply = $this->sourceFilter;
        $this->targetScope = $targetScope;
    }

    /**
     * Sets the HasMany property on the owning entity only (null or collection).
     * Does not update children's mappedBy / FK; that is handled at a higher level.
     */
    public function associate(EntityInterface $entity, ?EntityCollection $children): void
    {
        $entity->setRaw($this->propertyName, $children);
    }
}

