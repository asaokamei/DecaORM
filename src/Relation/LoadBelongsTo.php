<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\BelongsTo;
use WScore\DecaORM\Attribute\BelongsToOne;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

class LoadBelongsTo
{
    public function __construct(
        EntityInterface        $childEntity,
        BelongsTo|BelongsToOne $childRelation,
        RepositoryInterface    $targetRepository)
    {
        $parentId = $childEntity->get($childRelation->foreignKey);
        $parentEntity = $targetRepository->find($parentId);
        if (empty($parentEntity)) {
            $childEntity->set($childRelation->propertyName, null);
        } elseif (count($parentEntity) > 1) {
            throw new RuntimeException('BelongsTo relation must have only one parent.');
        } else {
            $parentEntity = $parentEntity[0];
            $childEntity->set($childRelation->propertyName, $parentEntity);
        }
    }
}