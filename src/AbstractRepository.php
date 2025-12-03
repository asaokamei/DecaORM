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
    public function find(int $id): ?EntityInterface
    {
        /** @var T|null $entity */
        $entity = $this->fetchEntityById($id);
        return $entity;
    }

    /**
     * @param array $data
     * @return T|null
     */
    public function createAndSave(array $data): ?EntityInterface
    {
        $id = $this->insertData($data);
        return $id
            ? $this->find($id)
            : null;
    }

    /**
     * UserエンティティをDBに保存（新規作成または更新）
     *
     * @param T $entity
     * @return T
     */
    public function save(EntityInterface $entity): ?EntityInterface
    {
        if ($entity->getId() === null) {
            $this->insertEntity($entity);
        } else {
            $this->updateEntity($entity);
        }
        /** @var T $entity */
        return $entity;
    }

    /**
     * @param T $entity
     */
    public function delete(EntityInterface $entity): void
    {
        $this->deleteEntity($entity);
    }

}