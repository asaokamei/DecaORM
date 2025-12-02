<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use PDO;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\RepositoryTrait;

class UserRepository
{
    use RepositoryTrait;

    public function __construct(PDO $pdo, HydratorInterface $hydrator = null)
    {
        $this->db = $pdo;
        $this->hydrator = $hydrator ?? new AttributeHydrator(User::class);
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

    /**
     * PostにUserを読み込む（ManyToOne）
     */
    public function loadUserForPost(Post $post): void
    {
        $this->fillParentEntity($post, 'user', 'user_id');
    }
}
