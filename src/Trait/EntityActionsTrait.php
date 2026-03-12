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

