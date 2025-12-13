<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\RepositoryInterface;

class LoadHasOne
{
    public function __construct(
        EntityInterface     $parentEntity,
        HasOne              $parentRelation,
        RepositoryInterface $targetRepository)
    {
        $parentProperty = $parentRelation->propertyName;
        $childProperty = $parentRelation->mappedBy;
        $childRelation = $targetRepository->getRelation($parentRelation->mappedBy);

        // Find posts by foreign key
        $children = $targetRepository->find($parentEntity->getId(), $childRelation->foreignKey);

        if (empty($children)) {
            $parentEntity->set($parentProperty, null);
            return;
        }
        if (count($children) > 1) {
            throw new RuntimeException('HasOne relation must have only one child.');
        }
        $child = $children[0];
        $child->set($childProperty, $parentEntity);
        $parentEntity->set($parentProperty, $child);
    }
}