<?php

namespace WScore\DecaORM\Relation;

use WScore\DecaORM\Attribute\HasMany;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

class LoadHasMany
{
    public function __construct(
        EntityInterface     $parentEntity,
        HasMany             $parentRelation,
        RepositoryInterface $targetRepository)
    {
        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;
        $childRelation = $targetRepository->getRelation($parentRelation->mappedBy);

        // Find posts by foreign key
        $children = $targetRepository->find($parentEntity->getId(), $childRelation->foreignKey, $parentRelation->orderBy);

        if (empty($children)) {
            $parentEntity->set($parentProperty, []);
            return;
        }

        // Set the bidirectional link (post -> user)
        foreach ($children as $child) {
            $child->set($childProperty, $parentEntity);
        }

        $parentEntity->set($parentProperty, $children);
    }
}