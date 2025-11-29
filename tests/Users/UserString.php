<?php

namespace WScore\DecaORM\Tests\Users;

use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\EntityTrait;

/**
 * すべてのコラムをstringとして扱うエンティティのサンプル
 */
class UserString implements EntityInterface
{
    use EntityTrait;

    // すべてのプロパティをstringとして定義
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at !== null ? new \DateTimeImmutable($this->created_at) : null;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at !== null ? new \DateTimeImmutable($this->updated_at) : null;
    }
}

