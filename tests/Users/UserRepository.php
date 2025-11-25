<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use WScore\DecaORM\RepositoryTrait;

class UserRepository
{
    use RepositoryTrait;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->hydrator = new UserHydrator();
        $this->now = new DateTimeImmutable();
    }

    public function find(int $id): ?User
    {
        return $this->fetchEntityById($id);
    }

    public function createAndSave(array $data): ?User
    {
        $id = $this->insertData($data);
        return $id
            ? $this->find($id)
            : null;
    }

    public function save(User $user): User
    {
        if ($user->getId() === null) {
            $data = $this->hydrator->dehydrate($user);
            return $this->createAndSave($data);
        } else {
            $this->updateEntity($user);
        }
        return $user;
    }

    public function delete(User $user): void
    {
        $this->deleteEntity($user);
    }
}
