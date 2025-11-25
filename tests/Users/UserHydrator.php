<?php

namespace WScore\DecaORM\Tests\Users;

use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\HydratorInterface;
use WScore\DecaORM\HydratorTrait;

class UserHydrator implements HydratorInterface
{
    use HydratorTrait;

    public function isPkAutoNumber(): bool
    {
        return true;
    }

    public function getEntityClass(): string
    {
        return User::class;
    }

    public function getTableName(): string
    {
        return 'users';
    }

    public function getPrimaryKey(): string
    {
        return 'id';
    }

    public function listProperties(): array
    {
        return ['id', 'name', 'email', 'created_at', 'updated_at'];
    }

    public function getCreatedAt(): ?string
    {
        return 'created_at';
    }

    public function getUpdatedAt(): ?string
    {
        return 'updated_at';
    }

    public function hydrate(array $data): EntityInterface
    {
        return $this->hydrateEntity(new User(), $data);
    }

    public function dehydrate(EntityInterface $entity): array
    {
        return $this->dehydrateEntity($entity);
    }
}
