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
     */
    public function load(string $relationName): Collection|EntityCollection|null
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
     * Synchronizes a BelongsTo/BelongsToOne relation.
     *
     * - Sets relation property (e.g. $this->user)
     * - Sets foreign key property (e.g. $this->user_id)
     * - If inversedBy points to a HasMany collection, updates it on both sides
     */
    protected function syncBelongsTo(string $relationName, ?EntityInterface $target): void
    {
        $repo = self::_repository();
        $relation = $repo->getRelation($relationName);
        if (!($relation instanceof BelongsTo) && !($relation instanceof BelongsToOne)) {
            throw new \RuntimeException('syncBelongsTo requires BelongsTo/BelongsToOne: ' . $relationName);
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

