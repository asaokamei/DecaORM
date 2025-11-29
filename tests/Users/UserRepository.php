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
        $user = $this->fetchEntityById($id);
        if (!$user instanceof User) {
            return null;
        }
        return $user;
    }

    public function createAndSave(array $data): ?User
    {
        $id = $this->insertData($data);
        return $id
            ? $this->find($id)
            : null;
    }

    public function save(User $user): void
    {
        if ($user->getId() === null) {
            $this->insertEntity($user);
        } else {
            $this->updateEntity($user);
        }
    }

    public function delete(User $user): void
    {
        $this->deleteEntity($user);
    }
}
