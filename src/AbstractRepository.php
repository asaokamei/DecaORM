<?php

namespace WScore\DecaORM;

use WScore\DecaORM\Trait\RepositoryTrait;

/**
 * @template T of EntityInterface
 */
abstract class AbstractRepository implements RepositoryInterface
{
    use RepositoryTrait;

    /**
     * IDに基づいてUserエンティティを取得
     *
     * @param int $id
     * @return T|null
     */
    public function findById(int|string $id): ?EntityInterface
    {
        $list = $this->find($id);
        return $list[0] ?? null;
    }

    public function createEntity(array $data): EntityInterface
    {
        $class = $this->hydrator->getEntityClass();
        $entity = new $class();
        foreach ($this->hydrator->listProperties() as $property) {
            $entity->set($property, $data[$property] ?? null);
        }
        return $entity;
    }

    /**
     * Creates and saves a new entity from data.
     * 
     * This is a convenience method that calls createEntity() and insertEntity().
     * For more flexible entity creation (e.g., with additional parameters),
     * implement a custom method in your repository class (e.g., PostRepository::create).
     * 
     * @param array $data
     * @return T|null
     */
    public function createAndSave(array $data): ?EntityInterface
    {
        $entity = $this->createEntity($data);
        $this->insertEntity($entity);
        return $entity;
    }

    /**
     * Saves an entity (insert or update).
     * 
     * @param EntityInterface $entity
     * @return void
     */
    public function save(EntityInterface $entity): void
    {
        if ($this->hydrator->isPkAutoNumber()) {
            if ($entity->getId() === null) {
                $this->insertEntity($entity);
            } else {
                $this->updateEntity($entity);
            }
            return;
        }
        if (EntityCache::has($this->hydrator->getEntityClass(), $entity->getId())) {
            $this->updateEntity($entity);
        } else {
            $this->insertEntity($entity);
        }
    }

    /**
     * Deletes an entity (alias for deleteEntity for backward compatibility).
     * 
     * @param EntityInterface $entity
     * @return void
     * @deprecated Use deleteEntity() instead. This method is kept for backward compatibility.
     */
    public function delete(EntityInterface $entity): void
    {
        $this->deleteEntity($entity);
    }

}