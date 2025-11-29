<?php

namespace WScore\DecaORM\Tests\Users;

use DateTimeImmutable;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\EntityTrait;

class User implements EntityInterface
{
    use EntityTrait;

    // Define all properties as string for read/write from DB
    public ?string $user_id = null;
    public string $name = '';
    public string $email = '';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function getId(): ?int
    {
        return $this->user_id !== null ? (int) $this->user_id : null;
    }

    public function setId(int|string $id): void
    {
        $this->user_id = (string) $id;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->created_at !== null ? new DateTimeImmutable($this->created_at) : null;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updated_at !== null ? new DateTimeImmutable($this->updated_at) : null;
    }
}