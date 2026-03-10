<?php

declare(strict_types=1);

namespace WScore\DecaORM\Tests\Support;

use WScore\DecaORM\Collection;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Tests\Fixtures\EntityActions\ActionUserRepository;

final class SpyActionUserRepository extends ActionUserRepository
{
    public ?EntityInterface $loadEntity = null;
    public ?string $loadRelationName = null;
    public Collection|EntityCollection|null $loadReturn = null;

    public function load(EntityInterface|array $entities, string $relationName): Collection|EntityCollection
    {
        $this->loadEntity = is_array($entities) ? ($entities[0] ?? null) : $entities;
        $this->loadRelationName = $relationName;
        $this->loadReturn = $this->loadReturn ?? new Collection([]);
        return $this->loadReturn;
    }
}

