<?php

namespace WScore\DecaORM;

use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Contacts\EntityInterface;
use WScore\DecaORM\Contacts\RepositoryInterface;

/**
 * This class is used to replicate an entity including its relations.
 * Usage:
 * 1. Created this instance.
 * 2. Load all related entities.
 * 3. Replicate the entity.
 * 4. Save the entity.
 */
class EntityHandler
{
    /**
     * @var EntityCollection[]
     */
    private array $related = [];

    /**
     * @var RepositoryInterface[]
     */
    private array $repositories = [];

    public function __construct(
        private EntityInterface $entity,
        private RepositoryInterface $repository
    ) {
        $this->repositories[get_class($entity)] = $repository;
    }

    /**
     * Replicates the entity and its related entities.
     * Only HasMany and HasOne relations are replicated.
     */
    public function replicate(): static
    {
        $entity = $this->replicateEntity($this->entity);
        return new static($entity, $this->repository);
    }

    private function replicateEntity(EntityInterface $entity): EntityInterface
    {
        $repository = $this->getRepository($entity);
        $replicated = $this->replicatorRaw($entity);
        foreach ($repository->getHydrator()->getRelations() as $relation)
        {
            if ($relation instanceof HasMany || $relation instanceof HasOne) {
                $related = $entity->get($relation->propertyName);
                if ($related instanceof EntityInterface) {
                    $related = $this->replicateEntity($related);
                    $this->magicSetter($replicated, $relation->propertyName, $related);
                } elseif (is_array($related)) {
                    $list = array_map([$this, 'replicateEntity'], $related);
                    $this->magicSetter($replicated, $relation->propertyName, $list);
                }
            }
        }

        return $replicated;
    }

    private function magicSetter(EntityInterface $entity, string $propertyName, $value): void
    {
        $method = 'set'.ucfirst($propertyName);
        if (method_exists($entity, $method)) {
            $entity->$method($value);
        } else {
            $entity->set($propertyName, $value);
        }
    }

    /**
     * loads related entities recursively.
     * example:
     * $replicator->load('user.address');
     */
    public function load(string $propertyName): void
    {
        $names = explode('.', $propertyName);
        $saves = '';
        foreach ($names as $name) {
            $saves = $saves ? $saves.'.'.$name: $name;
            if (!isset($this->related[$saves])) {
                if (isset($related)) {
                    $related = $related->load($name);
                } else {
                    $related = $this->repository->load($this->entity, $name);
                }
            } else {
                continue;
            }
            $this->related[$saves] = $related;
        }
    }

    /**
     * Saves the entity and its related entities to the database.
     */
    public function save(): void
    {
        $this->saveEntity($this->entity);
    }

    private function saveEntity(EntityInterface $entity): void
    {
        $repository = $this->getRepository($entity);
        $repository->save($entity);
        foreach ($repository->getHydrator()->getRelations() as $relation) {
            if ($relation instanceof HasMany || $relation instanceof HasOne) {
                $related = $entity->get($relation->propertyName);
                if ($related instanceof EntityInterface) {
                    $this->saveEntity($related);
                } elseif (is_array($related)) {
                    foreach ($related as $item) {
                        $this->saveEntity($item);
                    }
                }
            } elseif ($relation instanceof ManyToMany) {
                if (method_exists($repository, 'syncManyToMany')) {
                    $repository->syncManyToMany($entity, $relation->propertyName);
                }
            }
        }
    }

    private function getRepository(string|EntityInterface $entity): ?RepositoryInterface
    {
        if (!isset($this->repositories[get_class($entity)])) {
            $this->repositories[get_class($entity)] = $this->repository->getRepository($entity);
        }
        return $this->repositories[get_class($entity)];
    }

    public function getEntity(): EntityInterface
    {
        return $this->entity;
    }

    private function replicatorRaw(EntityInterface $entity): EntityInterface
    {
        $replicated = clone $entity;
        $repository = $this->getRepository($entity);

        $idKey = $repository->getHydrator()->getPrimaryKey();
        $replicated->set($idKey, null);

        $newAt = $repository->getHydrator()->getCreatedAt();
        if ($newAt) {
            $replicated->set($newAt, null);
        }
        $modAt = $repository->getHydrator()->getUpdatedAt();
        if ($newAt) {
            $replicated->set($modAt, null);
        }
        return $replicated;
    }

}