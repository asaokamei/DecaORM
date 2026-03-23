<?php

namespace WScore\DecaORM\Tests\Fixtures\Persistence;

use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Trait\EntityTrait;

/**
 * Minimal entity for persistence-hook integration tests (SQLite).
 */
#[Table(name: 'hook_items')]
#[Repository(HookItemRepository::class)]
class HookItem implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'hook_item_id')]
    private ?int $id = null;

    #[Column]
    private string $name = '';

    #[Column]
    private int $version = 1;

    #[Column]
    private int $tenant_id = 0;

    #[Column]
    private ?string $deleted_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getTenantId(): int
    {
        return $this->tenant_id;
    }

    public function setTenantId(int $tenantId): void
    {
        $this->tenant_id = $tenantId;
    }

    public function getDeletedAt(): ?string
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?string $deletedAt): void
    {
        $this->deleted_at = $deletedAt;
    }
}
