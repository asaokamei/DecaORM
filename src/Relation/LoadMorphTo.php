<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\MorphTo;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\OrmManager;

/**
 * Loads the polymorphic parent (MorphTo) from the child side.
 *
 * Parents may belong to different entity classes, so the result of {@see RepositoryInterface::load()}
 * for this relation is a generic {@see \WScore\DecaORM\Collection}, not {@see EntityCollection}
 * (which requires a single entity class). Resolution is therefore applied **per child entity**:
 * each row may point at a different repository/table; there is no single homogeneous batch result.
 */
class LoadMorphTo
{
    /**
     * @param EntityInterface|EntityCollection<EntityInterface> $entities
     * @return EntityInterface[]
     */
    public static function load(
        EntityInterface|EntityCollection $entities,
        MorphTo $childRelation,
    ): array {
        if ($entities instanceof EntityInterface) {
            return self::loadSingle($entities, $childRelation);
        }
        if (count($entities) === 0) {
            return [];
        }

        $allParents = [];
        foreach ($entities as $child) {
            if (!$child instanceof EntityInterface) {
                continue;
            }
            $allParents = array_merge($allParents, self::loadSingle($child, $childRelation));
        }

        return array_unique($allParents, SORT_REGULAR);
    }

    /**
     * @return EntityInterface[]
     */
    private static function loadSingle(
        EntityInterface $childEntity,
        MorphTo $childRelation,
    ): array {
        $parent = self::resolveParent($childEntity, $childRelation);
        $childEntity->setRaw($childRelation->propertyName, $parent);
        return $parent ? [$parent] : [];
    }

    private static function resolveParent(EntityInterface $childEntity, MorphTo $childRelation): ?EntityInterface
    {
        $disc = $childEntity->getRaw($childRelation->typeColumn);
        $fid = $childEntity->getRaw($childRelation->foreignKey);
        if ($disc === null || $fid === null) {
            return null;
        }
        $disc = (string) $disc;
        if (!isset($childRelation->typeMap[$disc])) {
            throw new RuntimeException('MorphTo: unknown discriminator: ' . $disc);
        }
        $class = $childRelation->typeMap[$disc];
        $repo = OrmManager::getRepository($class::getRepositoryClass());
        return $repo->findById($fid);
    }
}
