<?php

namespace WScore\DecaORM\Trait;

use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Attribute\MorphTo;
use WScore\DecaORM\Attribute\MorphToOne;
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
                || $relation instanceof MorphTo
                || $relation instanceof MorphToOne
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
            $this->associateBelongsTo($relation, $targetOrTargets);
            return;
        }
        if ($relation instanceof MorphTo || $relation instanceof MorphToOne) {
            if ($targetOrTargets !== null && !$targetOrTargets instanceof EntityInterface) {
                throw new \InvalidArgumentException('associate for MorphTo/MorphToOne expects EntityInterface|null, got ' . get_debug_type($targetOrTargets));
            }
            $this->associateMorphTo($relation, $targetOrTargets);
            return;
        }
        if ($relation instanceof HasOne) {
            if ($targetOrTargets !== null && !$targetOrTargets instanceof EntityInterface) {
                throw new \InvalidArgumentException('associate for HasOne expects EntityInterface|null, got ' . get_debug_type($targetOrTargets));
            }
            $this->associateHasOne($relation, $targetOrTargets);
            return;
        }
        if ($relation instanceof HasMany) {
            $this->associateHasMany($relation, $targetOrTargets);
            return;
        }
        if ($relation instanceof ManyToMany) {
            $this->associateManyToMany($relation, $targetOrTargets);
            return;
        }
        throw new \RuntimeException('Unsupported relation type for associate: ' . $relationName);
    }

    /**
     * Associates a BelongsTo/BelongsToOne relation.
     *
     * Delegates owning-side assignment to the attribute's {@see BelongsTo::associate} /
     * {@see BelongsToOne::associate}, then updates inverse HasMany collections when inversedBy is set.
     */
    protected function associateBelongsTo(BelongsTo|BelongsToOne $relation, ?EntityInterface $target): void
    {
        $prop = $relation->propertyName;
        $current = $this->getRaw($prop);
        if ($current === $target) {
            return;
        }

        $relation->associate($this, $target);

        // Keep inverse side in memory when already loaded (HasMany collection or HasOne single ref).
        if ($relation->inversedBy === null) {
            return;
        }
        $inverseName = $relation->inversedBy;

        if ($current instanceof EntityInterface) {
            $inverse = $current->getRaw($inverseName);
            $inverseRel = OrmManager::getRepository($current::getRepositoryClass())->getRelation($inverseName);
            if ($inverseRel instanceof HasMany || $inverseRel instanceof ManyToMany) {
                if ($inverse instanceof EntityCollection) {
                    $inverse->delEntity($this);
                }
            } elseif ($inverse === $this) {
                $current->setRaw($inverseName, null);
            }
        }
        if ($target instanceof EntityInterface) {
            $inverse = $target->getRaw($inverseName);
            $inverseRel = OrmManager::getRepository($target::getRepositoryClass())->getRelation($inverseName);
            if ($inverseRel instanceof HasMany || $inverseRel instanceof ManyToMany) {
                if ($inverse instanceof EntityCollection && !$inverse->hasEntity($this)) {
                    $inverse->add($this);
                }
            } elseif ($inverse === null || $inverse === $this) {
                $target->setRaw($inverseName, $this);
            }
        }
    }

    /**
     * Same inverse bookkeeping as {@see associateBelongsTo} for polymorphic owning sides.
     */
    protected function associateMorphTo(MorphTo|MorphToOne $relation, ?EntityInterface $target): void
    {
        $prop = $relation->propertyName;
        $current = $this->getRaw($prop);
        if ($current === $target) {
            return;
        }

        $relation->associate($this, $target);

        if ($relation->inversedBy === null) {
            return;
        }
        $inverseName = $relation->inversedBy;

        if ($current instanceof EntityInterface) {
            $inverse = $current->getRaw($inverseName);
            $inverseRel = OrmManager::getRepository($current::getRepositoryClass())->getRelation($inverseName);
            if ($inverseRel instanceof HasMany || $inverseRel instanceof ManyToMany) {
                if ($inverse instanceof EntityCollection) {
                    $inverse->delEntity($this);
                }
            } elseif ($inverse === $this) {
                $current->setRaw($inverseName, null);
            }
        }
        if ($target instanceof EntityInterface) {
            $inverse = $target->getRaw($inverseName);
            $inverseRel = OrmManager::getRepository($target::getRepositoryClass())->getRelation($inverseName);
            if ($inverseRel instanceof HasMany || $inverseRel instanceof ManyToMany) {
                if ($inverse instanceof EntityCollection && !$inverse->hasEntity($this)) {
                    $inverse->add($this);
                }
            } elseif ($inverse === null || $inverse === $this) {
                $target->setRaw($inverseName, $this);
            }
        }
    }

    /**
     * Associates a HasOne relation (one-to-one, inverse side).
     *
     * Owning-side property is set via {@see HasOne::associate}; the target's owning BelongsTo is updated
     * via the target entity's {@see associate()} so inverse handling stays in one place.
     */
    protected function associateHasOne(HasOne $relation, ?EntityInterface $target): void
    {
        $prop = $relation->propertyName;
        $current = $this->getRaw($prop);
        if ($current === $target) {
            return;
        }

        $relation->associate($this, $target);

        $mappedBy = $relation->mappedBy;

        if ($current instanceof EntityInterface) {
            $current->associate($mappedBy, null);
        }

        if ($target instanceof EntityInterface) {
            $target->associate($mappedBy, $this);
        }
    }

    /**
     * Builds a filtered {@see EntityCollection} for in-memory HasMany association.
     * Placed here for now; a more specific home may be chosen later.
     *
     * @param iterable<EntityInterface>|EntityCollection $children
     */
    protected function normalizeHasManyChildren(
        RepositoryInterface $ownerRepo,
        HasMany $relation,
        iterable|EntityCollection $children
    ): EntityCollection {
        $targetRepo = $relation->targetEntity ? $ownerRepo->getRepository($relation->targetEntity) : $ownerRepo;
        $newChildren = $children instanceof EntityCollection
            ? $children
            : new EntityCollection(
                is_array($children) ? $children : iterator_to_array($children),
                $targetRepo
            );

        $filtered = [];
        foreach ($newChildren as $c) {
            if ($c instanceof EntityInterface) {
                $filtered[] = $c;
            }
        }

        return new EntityCollection($filtered, $targetRepo);
    }

    /**
     * Associates a HasMany relation (one-to-many, inverse side).
     *
     * Collection shape is built via {@see self::normalizeHasManyChildren} and assigned with
     * {@see HasMany::associate}; children update their owning BelongsTo via {@see associate()}.
     *
     * @param iterable<EntityInterface>|EntityCollection|null $children
     */
    protected function associateHasMany(HasMany $relation, iterable|EntityCollection|null $children): void
    {
        $repo = self::_repository();
        $mappedBy = $relation->mappedBy;
        $prop = $relation->propertyName;
        $current = $this->getRaw($prop);

        if ($children === null) {
            if ($current instanceof EntityCollection) {
                foreach ($current as $oldChild) {
                    if ($oldChild instanceof EntityInterface) {
                        $oldChild->associate($mappedBy, null);
                    }
                }
            }
            $relation->associate($this, null);
            return;
        }

        $newChildren = $this->normalizeHasManyChildren($repo, $relation, $children);

        if ($current instanceof EntityCollection) {
            foreach ($current as $oldChild) {
                if (!$oldChild instanceof EntityInterface) {
                    continue;
                }
                if (!$newChildren->hasEntity($oldChild)) {
                    $oldChild->associate($mappedBy, null);
                }
            }
        }

        $relation->associate($this, $newChildren);

        foreach ($newChildren as $child) {
            if (!$child instanceof EntityInterface) {
                continue;
            }
            $child->associate($mappedBy, $this);
        }
    }

    /**
     * Associates a ManyToMany relation on the entity side (in-memory only).
     *
     * Delegates to {@see ManyToMany::associate}. Does not write the join table; use the repository's
     * syncManyToMany() to persist join-table changes.
     *
     * @param iterable<EntityInterface>|EntityCollection|null $targets
     */
    protected function associateManyToMany(ManyToMany $relation, iterable|EntityCollection|null $targets): void
    {
        $relation->associate(self::_repository(), $this, $targets);
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
        $prop = $relation->propertyName;
        $current = $this->getRaw($prop);
        if (!($current instanceof EntityCollection)) {
            $current = new EntityCollection([], $targetRepo);
            $this->setRaw($prop, $current);
        }

        if ($current->hasEntity($child)) {
            return;
        }
        $current->add($child);

        $child->associate($relation->mappedBy, $this);
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

        $prop = $relation->propertyName;
        $current = $this->getRaw($prop);
        if ($current instanceof EntityCollection) {
            $current->delEntity($child);
        }

        $child->associate($relation->mappedBy, null);
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

