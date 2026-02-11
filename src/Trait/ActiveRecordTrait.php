<?php

namespace WScore\DecaORM\Trait;

use WScore\DecaORM\Collection;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryManager;
use WScore\DecaORM\RepositoryInterface;

/**
 * @mixin EntityInterface
 * @mixin EntityTrait
 */
trait ActiveRecordTrait
{
    /**
     * @return RepositoryInterface
     */
    protected static function _repository(): RepositoryInterface
    {
        return RepositoryManager::get(self::getRepositoryClass());
    }

    /**
     * Saves the entity.
     *
     * @return static
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
        self::_repository()->deleteEntity($this);
    }

    /**
     * Creates a new entity.
     * Does NOT save this to a database.
     *
     * @param array $data
     * @return static|EntityInterface
     */
    public static function create(array $data): static|EntityInterface
    {
        return self::_repository()->createEntity($data);
    }

    /**
     * Fills the entity with data.
     *
     * @param array $data
     * @return static
     */
    public function fill(array $data): static
    {
        $hydrator = self::_repository()->getHydrator();
        $idProp = $hydrator->getPrimaryKey();
        $createdProp = $hydrator->getCreatedAt();
        $updatedProp = $hydrator->getUpdatedAt();

        $fillable = property_exists(static::class, 'fillable') ? static::$fillable : null;
        $guarded = property_exists(static::class, 'guarded') ? static::$guarded : [];

        foreach ($data as $key => $value) {
            if ($key === $idProp || $key === $createdProp || $key === $updatedProp) {
                continue;
            }
            if (is_array($fillable) && !in_array($key, $fillable)) {
                continue;
            }
            if (is_array($guarded) && in_array($key, $guarded)) {
                continue;
            }
            if (method_exists($this, 'isFillable') && !$this->isFillable($key)) {
                continue;
            }
            $setter = 'set' . ucfirst($key);
            if (method_exists($this, $setter)) {
                $this->$setter($value);
            } else {
                $this->set($key, $value);
            }
        }
        return $this;
    }

    /**
     * Finds an entity by ID.
     *
     * @param int|string $id
     * @return static|null
     */
    public static function findById(int|string $id): ?EntityInterface
    {
        return self::_repository()->findById($id);
    }

    /**
     * Loads a relation.
     *
     * @param string $relationName
     * @return Collection|EntityCollection
     */
    public function loadRelation(string $relationName): Collection|EntityCollection
    {
        return self::_repository()->load($this, $relationName);
    }
}