<?php

namespace WScore\DecaORM;

/**
 * @template T of EntityInterface
 */
abstract class AbstractRepository
{
    use RepositoryTrait;

    /**
     * IDに基づいてUserエンティティを取得
     *
     * @param int $id
     * @return T|null
     */
    public function findById(int $id): ?EntityInterface
    {
        $list = $this->fetch($id);
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
     * UserエンティティをDBに保存（新規作成または更新）
     *
     * @param T $entity
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
     * @param T $entity
     */
    public function delete(EntityInterface $entity): void
    {
        $this->deleteEntity($entity);
    }

}