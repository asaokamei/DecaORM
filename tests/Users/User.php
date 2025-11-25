<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\EntityTrait;

class User implements EntityInterface
{
    use EntityTrait;

    private ?int $user_id = null;
    private string $name;
    private string $email;
    private ?DateTimeImmutable $created_at = null;
    private ?DateTimeImmutable $updated_at = null;

    public function getId(): ?int
    {
        return $this->user_id;
    }

    public function setId(int|string $id): void
    {
        $this->user_id = (int) $id;
    }

    public function setCreatedAt(string|DateTimeImmutable $created_at): void
    {
        $this->setDateTimeProperty('created_at', $created_at);
    }

    public function setUpdatedAt(string|DateTimeImmutable $updated_at): void
    {
        $this->setDateTimeProperty('updated_at', $updated_at);
    }
}