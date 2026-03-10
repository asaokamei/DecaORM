<?php

namespace WScore\DecaORM;

use PDO;
use WScore\DecaORM\Contacts\EntityInterface;
use WScore\DecaORM\Contacts\RepositoryInterface;
use WScore\DecaORM\Trait\RepositoryTrait;

/**
 * @template T of EntityInterface
 */
abstract class AbstractRepository implements RepositoryInterface
{
    use RepositoryTrait;

    /**
     * Sets up the repository.
     * This method is called by the constructor of the repository.
     * 
     * set pdo to use specific database connection.
     * Or, use container/RepositoryManager to get the database connection.
     * 
     * set entityClass to create generic attribute hydrator for the entity class.
     * Or, set specific hydrator before calling this method.
     * 
     * @param RepositoryManager $manager
     * @param PDO|null $pdo
     * @param string|null $entityClass
     * @return void
     */
    protected function setUpRepository(RepositoryManager $manager, ?PDO $pdo = null, ?string $entityClass = null): void
    {
        $this->manager = $manager;
        $this->db = $pdo ?? $manager->getPDO();
        if ($entityClass !== null) {
            $this->hydrator = new AttributeHydrator($entityClass);
        }
        $this->now = $manager->getDateTimeImmutable() ?? new \DateTimeImmutable();
        if (!isset($this->hydrator)) {
            throw new \RuntimeException('Hydrator is not set');
        }
    }

    /**
     * IDに基づいてUserエンティティを取得
     *
     * @param int|string $id
     * @return EntityInterface|T|null
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
        if (method_exists($entity, 'fill')) {
            $entity->fill($data);
        } else {
            foreach ($this->hydrator->listProperties() as $property) {
                $entity->set($property, $data[$property] ?? null);
            }
        }
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

    public function delete(EntityInterface $entity): void
    {
        $this->forceDelete($entity);
    }

    public function makeHandler(EntityInterface $entity): EntityHandler
    {
        return new EntityHandler($entity, $this);
    }
}