<?php

namespace WScore\DecaORM;

class SampleRepository
{
    use RepositoryTrait;

    /**
     * IDに基づいてUserエンティティを取得
     */
    public function find(int $id): ?EntityInterface
    {
        return $this->fetchEntityById($id);
    }

    public function createAndSave(array $data): ?EntityInterface
    {
        unset($data[$this->hydrator->getPrimaryKey()]);
        $id = $this->insertData($data);
        return $id
            ? $this->find($id)
            : null;
    }

    /**
     * UserエンティティをDBに保存（新規作成または更新）
     */
    public function save(EntityInterface $user): ?EntityInterface
    {
        if ($user->getId() === null) {
            return $this->createAndSave($this->hydrator->dehydrate($user));
        } else {
            $this->updateEntity($user);
        }
        return $user;
    }

    public function delete(EntityInterface $user): void
    {
        $this->deleteEntity($user);
    }

}