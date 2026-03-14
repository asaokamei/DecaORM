<?php

namespace WScore\DecaORM\Trait;

use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Attribute\CustomLoader;
use WScore\DecaORM\Collection;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\EntityHandler;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Contracts\RepositoryInterface;

/**
 * Entity actions that delegate to a repository/handler.
 *
 * @mixin EntityInterface
 * @mixin EntityTrait
 */
trait EntityActionsTrait
{
    /**
     * @return RepositoryInterface
     */
    protected static function _repository(): RepositoryInterface
    {
        return OrmManager::getRepository(self::getRepositoryClass());
    }

    /**
     * Saves the entity.
     */
    public function save(): static
    {
        self::_repository()->save($this);
        return $this;
    }

    /**
     * Deletes the entity.
     */
    public function delete(): void
    {
        self::_repository()->delete($this);
    }

    /**
     * Loads a relation.
     *
     * @return Collection|EntityCollection|EntityInterface|null For HasOne/BelongsTo/BelongsToOne returns single entity or null; for HasMany/ManyToMany returns collection.
     */
    public function load(string $relationName): Collection|EntityCollection|EntityInterface|null
    {
        $found = $this->getRaw($relationName);
        if ($found !== null) {
            return $found;
        }
        if ($this->isNew()) {
            $relation = self::_repository()->getRelation($relationName);
            if (
                $relation instanceof HasOne
                || $relation instanceof BelongsTo
                || $relation instanceof BelongsToOne
            ) {
                $this->setRaw($relationName, null);
                return null;
            }
            if (
                $relation instanceof HasMany
                || $relation instanceof ManyToMany
                || $relation instanceof CustomLoader
            ) {
                $repo = self::_repository();
                $targetRepo = $relation->targetEntity ? $repo->getRepository($relation->targetEntity) : $repo;
                $empty = new EntityCollection([], $targetRepo);
                $this->setRaw($relationName, $empty);
                return $empty;
            }
        }
        return self::_repository()->load($this, $relationName);
    }

    /**
     * Associates a relation by name. Dispatches to the appropriate method
     * based on the relation type (BelongsTo/BelongsToOne → single entity,
     * HasOne → single entity, HasMany/ManyToMany → iterable/collection).
     * In-memory only; use repository's syncManyToMany() to persist M:N join table.
     *
     * @param string $relationName Relation property name (e.g. 'user', 'posts')
     * @param EntityInterface|iterable|EntityCollection|null $targetOrTargets For BelongsTo/BelongsToOne/HasOne: single entity or null; for HasMany/ManyToMany: iterable/EntityCollection or null
     */
    public function associate(string $relationName, EntityInterface|iterable|EntityCollection|null $targetOrTargets): void
    {
        $repo = self::_repository();
        $relation = $repo->getRelation($relationName);

        if ($relation instanceof BelongsTo || $relation instanceof BelongsToOne) {
            if ($targetOrTargets !== null && !$targetOrTargets instanceof EntityInterface) {
                throw new \InvalidArgumentException('associate for BelongsTo/BelongsToOne expects EntityInterface|null, got ' . get_debug_type($targetOrTargets));
            }
            $this->associateBelongsTo($relationName, $targetOrTargets);
            return;
        }
        if ($relation instanceof HasOne) {
            if ($targetOrTargets !== null && !$targetOrTargets instanceof EntityInterface) {
                throw new \InvalidArgumentException('associate for HasOne expects EntityInterface|null, got ' . get_debug_type($targetOrTargets));
            }
            $this->associateHasOne($relationName, $targetOrTargets);
            return;
        }
        if ($relation instanceof HasMany) {
            $this->associateHasMany($relationName, $targetOrTargets);
            return;
        }
        if ($relation instanceof ManyToMany) {
            $this->associateManyToMany($relationName, $targetOrTargets);
            return;
        }
        throw new \RuntimeException('Unsupported relation type for associate: ' . $relationName);
    }

    /**
     * Associates a BelongsTo/BelongsToOne relation.
     *
     * - Sets relation property (e.g. $this->user)
     * - Sets foreign key property (e.g. $this->user_id)
     * - If inversedBy points to a HasMany collection, updates it on both sides
     */
    protected function associateBelongsTo(string $relationName, ?EntityInterface $target): void
    {
        $repo = self::_repository();
        $relation = $repo->getRelation($relationName);
        if (!($relation instanceof BelongsTo) && !($relation instanceof BelongsToOne)) {
            throw new \RuntimeException('associateBelongsTo requires BelongsTo/BelongsToOne: ' . $relationName);
        }

        $current = $this->getRaw($relationName);
        if ($current === $target) {
            return;
        }

        // In this ORM, relation foreignKey is a property name (used with getRaw/setRaw).
        $fkProp = $relation->foreignKey;

        // Set new relation + FK
        $this->setRaw($relationName, $target);
        $id = $target?->getId();
        $this->setRaw($fkProp, $id !== null ? (string) $id : null);

        // Update inverse HasMany, if configured.
        if ($relation->inversedBy === null) {
            return;
        }
        $inverseName = $relation->inversedBy;

        // If inverse collection is already loaded, keep it consistent in-memory.
        // (Avoid triggering DB loads here; setters should not cause queries.)
        if ($current instanceof EntityInterface) {
            $inverse = $current->getRaw($inverseName);
            if ($inverse instanceof EntityCollection) {
                $inverse->delEntity($this);
            }
        }
        if ($target instanceof EntityInterface) {
            $inverse = $target->getRaw($inverseName);
            if ($inverse instanceof EntityCollection && !$inverse->hasEntity($this)) {
                $inverse->add($this);
            }
        }
    }

    /**
     * Associates a HasOne relation (one-to-one, inverse side).
     *
     * - Sets relation property on this entity (e.g. $this->profile)
     * - Updates the target entity's BelongsTo/BelongsToOne (mappedBy) + its foreign key property
     * - Keeps inverse properties consistent only when already loaded (no DB loads in setters)
     */
    protected function associateHasOne(string $relationName, ?EntityInterface $target): void
    {
        $repo = self::_repository();
        $relation = $repo->getRelation($relationName);
        if (!($relation instanceof HasOne)) {
            throw new \RuntimeException('associateHasOne requires HasOne: ' . $relationName);
        }

        $current = $this->getRaw($relationName);
        if ($current === $target) {
            return;
        }

        // Set the relation on this entity first.
        $this->setRaw($relationName, $target);

        $mappedBy = $relation->mappedBy;

        // Detach current target (if any)
        if ($current instanceof EntityInterface) {
            self::syncMappedBelongsToOnTarget(target: $current, mappedBy: $mappedBy, parent: null);
        }

        // Attach new target (if any)
        if ($target instanceof EntityInterface) {
            self::syncMappedBelongsToOnTarget(target: $target, mappedBy: $mappedBy, parent: $this);
        }
    }

    /**
     * Associates a HasMany relation (one-to-many, inverse side).
     *
     * - Sets relation property on this entity to an EntityCollection
     * - Updates each child entity's BelongsTo/BelongsToOne (mappedBy) + its foreign key property
     * - Keeps things in-memory only (no DB loads in setters)
     *
     * @param string $relationName HasMany property name on this entity (e.g. 'posts')
     * @param iterable<EntityInterface>|EntityCollection|null $children
     */
    protected function associateHasMany(string $relationName, iterable|EntityCollection|null $children): void
    {
        $repo = self::_repository();
        $relation = $repo->getRelation($relationName);
        if (!($relation instanceof HasMany)) {
            throw new \RuntimeException('associateHasMany requires HasMany: ' . $relationName);
        }

        $mappedBy = $relation->mappedBy;
        $current = $this->getRaw($relationName);

        if ($children === null) {
            // Clear relation: detach all current children, then set to null
            if ($current instanceof EntityCollection) {
                foreach ($current as $oldChild) {
                    if ($oldChild instanceof EntityInterface) {
                        self::syncMappedBelongsToOnTarget(target: $oldChild, mappedBy: $mappedBy, parent: null);
                    }
                }
            }
            $this->setRaw($relationName, null);
            return;
        }

        $targetRepo = $relation->targetEntity ? $repo->getRepository($relation->targetEntity) : $repo;
        $newChildren = $children instanceof EntityCollection
            ? $children
            : new EntityCollection(
                is_array($children) ? $children : iterator_to_array($children),
                $targetRepo
            );

        // Filter invalid items defensively
        $filtered = [];
        foreach ($newChildren as $c) {
            if ($c instanceof EntityInterface) {
                $filtered[] = $c;
            }
        }
        $newChildren = new EntityCollection($filtered, $targetRepo);

        if ($current instanceof EntityCollection) {
            // Detach children that are no longer present
            foreach ($current as $oldChild) {
                if (!$oldChild instanceof EntityInterface) {
                    continue;
                }
                if (!$newChildren->hasEntity($oldChild)) {
                    self::syncMappedBelongsToOnTarget(target: $oldChild, mappedBy: $mappedBy, parent: null);
                }
            }
        }

        // Set collection on this entity
        $this->setRaw($relationName, $newChildren);

        // Attach/update all children
        foreach ($newChildren as $child) {
            if (!$child instanceof EntityInterface) {
                continue;
            }
            self::syncMappedBelongsToOnTarget(target: $child, mappedBy: $mappedBy, parent: $this);
        }
    }

    /**
     * Associates a ManyToMany relation on the entity side (in-memory only).
     *
     * Sets the in-memory collection; does not write to the join table.
     * Use repository's syncManyToMany() to persist join-table changes.
     *
     * @param string $relationName ManyToMany property name on this entity (e.g. 'roles')
     * @param iterable<EntityInterface>|EntityCollection|null $targets
     */
    protected function associateManyToMany(string $relationName, iterable|EntityCollection|null $targets): void
    {
        $repo = self::_repository();
        $relation = $repo->getRelation($relationName);
        if (!($relation instanceof ManyToMany)) {
            throw new \RuntimeException('associateManyToMany requires ManyToMany: ' . $relationName);
        }

        if ($targets === null) {
            $this->setRaw($relationName, null);
            return;
        }

        $targetRepo = $relation->targetEntity ? $repo->getRepository($relation->targetEntity) : null;

        $list = [];
        if ($targets instanceof EntityCollection) {
            foreach ($targets as $t) {
                if ($t instanceof EntityInterface) {
                    $list[] = $t;
                }
            }
        } elseif ($targets !== null) {
            foreach ($targets as $t) {
                if ($t instanceof EntityInterface) {
                    $list[] = $t;
                }
            }
        }

        // De-duplicate by reference or ID
        $seenRef = [];
        $seenId = [];
        $list2 = [];
        foreach ($list as $t) {
            $id = $t->getId();
            if ($id !== null) {
                if (isset($seenId[(string) $id])) {
                    continue;
                }
                $seenId[(string) $id] = true;
            } else {
                // avoid duplicate references for unsaved entities
                $hash = spl_object_hash($t);
                if (isset($seenRef[$hash])) {
                    continue;
                }
                $seenRef[$hash] = true;
            }
            $list2[] = $t;
        }

        $collection = new EntityCollection($list2, $targetRepo);
        $this->setRaw($relationName, $collection);
    }

    /**
     * Adds a single child entity to a HasMany relation and syncs FK/backref using metadata.
     *
     * This method does not trigger DB loads. If the HasMany collection is not loaded yet,
     * it will initialize it as an in-memory EntityCollection.
     */
    protected function addHasMany(string $relationName, EntityInterface $child): void
    {
        $repo = self::_repository();
        $relation = $repo->getRelation($relationName);
        if (!($relation instanceof HasMany)) {
            throw new \RuntimeException('addHasMany requires HasMany: ' . $relationName);
        }

        $targetRepo = $relation->targetEntity ? $repo->getRepository($relation->targetEntity) : $repo;
        $current = $this->getRaw($relationName);
        if (!($current instanceof EntityCollection)) {
            $current = new EntityCollection([], $targetRepo);
            $this->setRaw($relationName, $current);
        }

        if ($current->hasEntity($child)) {
            return;
        }
        $current->add($child);

        self::syncMappedBelongsToOnTarget(target: $child, mappedBy: $relation->mappedBy, parent: $this);
    }

    /**
     * Removes a single child entity from a HasMany relation and clears FK/backref (in-memory only).
     *
     * This method does not trigger DB loads. If the collection is not loaded, it will still
     * clear the child's mappedBy side.
     */
    protected function removeHasMany(string $relationName, EntityInterface $child): void
    {
        $repo = self::_repository();
        $relation = $repo->getRelation($relationName);
        if (!($relation instanceof HasMany)) {
            throw new \RuntimeException('removeHasMany requires HasMany: ' . $relationName);
        }

        $current = $this->getRaw($relationName);
        if ($current instanceof EntityCollection) {
            $current->delEntity($child);
        }

        self::syncMappedBelongsToOnTarget(target: $child, mappedBy: $relation->mappedBy, parent: null);
    }

    /**
     * Updates a target entity that owns the FK (BelongsTo/BelongsToOne) for a HasOne relation.
     *
     * mappedBy is the BelongsTo/BelongsToOne property name on the target entity.
     */
    private static function syncMappedBelongsToOnTarget(
        EntityInterface $target,
        string $mappedBy,
        ?EntityInterface $parent
    ): void {
        $targetRepo = OrmManager::getRepository($target::getRepositoryClass());
        $belongs = $targetRepo->getRelation($mappedBy);
        if (!($belongs instanceof BelongsTo) && !($belongs instanceof BelongsToOne)) {
            throw new \RuntimeException('HasOne mappedBy must point to BelongsTo/BelongsToOne: ' . $mappedBy);
        }

        $fkProp = $belongs->foreignKey; // property name

        $oldParent = $target->getRaw($mappedBy);
        if ($oldParent === $parent) {
            return;
        }

        // Set FK owner side
        $target->setRaw($mappedBy, $parent);
        $target->setRaw($fkProp, $parent?->getId());

        // If old parent had inverse loaded and it pointed to this target, clear it.
        if ($belongs->inversedBy !== null && $oldParent instanceof EntityInterface) {
            if ($oldParent->getRaw($belongs->inversedBy) === $target) {
                $oldParent->setRaw($belongs->inversedBy, null);
            }
        }
        // If new parent has inverse already loaded (or is being set via its own setter), set it.
        if ($belongs->inversedBy !== null && $parent instanceof EntityInterface) {
            $existing = $parent->getRaw($belongs->inversedBy);
            if ($existing === null || $existing === $target) {
                $parent->setRaw($belongs->inversedBy, $target);
            }
        }
    }

    public function getHandler(): EntityHandler
    {
        return new EntityHandler($this, self::_repository());
    }

    public function replicate(): EntityHandler
    {
        return $this->getHandler()->replicate();
    }

    public function isNew(): bool
    {
        return self::_repository()->isNew($this);
    }
}

