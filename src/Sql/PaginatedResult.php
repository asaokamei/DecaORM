<?php

namespace WScore\DecaORM\Sql;

use WScore\DecaORM\EntityCollection;

class PaginatedResult
{
    public function __construct(
        private readonly EntityCollection $items,
        private readonly int $totalCount,
        private readonly int $perPage,
        private readonly int $currentPage
    ) {
    }

    public function getItems(): EntityCollection
    {
        return $this->items;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getLastPage(): int
    {
        return (int) ceil($this->totalCount / $this->perPage);
    }

    public function hasPages(): bool
    {
        return $this->totalCount > $this->perPage;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->getLastPage();
    }

    public function getFrom(): int
    {
        if ($this->totalCount === 0) {
            return 0;
        }
        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function getTo(): int
    {
        if ($this->totalCount === 0) {
            return 0;
        }
        return min($this->currentPage * $this->perPage, $this->totalCount);
    }
}
