<?php

namespace WScore\DecaORM;

use PDO;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
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
     * @param OrmManager $manager
     * @param PDO|null $pdo
     * @param string|null $entityClass
     * @return void
     */
    protected function setUpRepository(OrmManager $manager, ?PDO $pdo = null, ?string $entityClass = null): void
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
        foreach ($this->hydrator->listProperties() as $property) {
            $entity->setRaw($property, $data[$property] ?? null);
        }
        $this->attachOrmContext($entity);
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
        if ($this->isNew($entity)) {
            $this->insertEntity($entity);
        } else {
            $this->updateEntity($entity);
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