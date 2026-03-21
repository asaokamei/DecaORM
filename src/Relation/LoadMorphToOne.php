<?php

namespace WScore\DecaORM\Relation;

use RuntimeException;
use WScore\DecaORM\Attribute\HasOne;
use WScore\DecaORM\Attribute\MorphToOne;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\EntityCollection;
use WScore\DecaORM\OrmManager;

/**
 * Loads the polymorphic parent (MorphToOne) from the child side.
 *
 * See {@see LoadMorphTo} class docblock: mixed parent classes forbid {@see EntityCollection} as the
 * load result; callers get a {@see \WScore\DecaORM\Collection}. Parents are resolved per child entity.
 */
class LoadMorphToOne
{
    /**
     * @param EntityInterface|EntityCollection<EntityInterface> $entities
     * @return EntityInterface[]
     */
    public static function load(
        EntityInterface|EntityCollection $entities,
        MorphToOne $childRelation,
    ): array {
        if ($entities instanceof EntityInterface) {
            return self::loadSingle($entities, $childRelation);
        }
        if (count($entities) === 0) {
            return [];
        }

        if (count($entities) > 1) {
            $pairCounts = [];
            foreach ($entities as $child) {
                if (!$child instanceof EntityInterface) {
                    continue;
                }
                $d = $child->getRaw($childRelation->typeColumn);
                $f = $child->getRaw($childRelation->foreignKey);
                if ($d === null || $f === null) {
                    continue;
                }
                $key = (string) $d . "\0" . (string) $f;
                $pairCounts[$key] = ($pairCounts[$key] ?? 0) + 1;
            }
            foreach ($pairCounts as $cnt) {
                if ($cnt > 1) {
                    throw new RuntimeException('MorphToOne: multiple children for the same morph parent (type+id)');
                }
            }
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
        MorphToOne $childRelation,
    ): array {
        $parent = self::resolveParent($childEntity, $childRelation);
        $childEntity->setRaw($childRelation->propertyName, $parent);

        if ($parent !== null && $childRelation->inversedBy !== null) {
            self::setChildOnParent($parent, $childRelation->inversedBy, $childEntity);
        }

        return $parent ? [$parent] : [];
    }

    private static function resolveParent(EntityInterface $childEntity, MorphToOne $childRelation): ?EntityInterface
    {
        $disc = $childEntity->getRaw($childRelation->typeColumn);
        $fid = $childEntity->getRaw($childRelation->foreignKey);
        if ($disc === null || $fid === null) {
            return null;
        }
        $disc = (string) $disc;
        if (!isset($childRelation->typeMap[$disc])) {
            throw new RuntimeException('MorphToOne: unknown discriminator: ' . $disc);
        }
        $class = $childRelation->typeMap[$disc];
        $repo = OrmManager::getRepository($class::getRepositoryClass());
        return $repo->findById($fid);
    }

    private static function setChildOnParent(
        EntityInterface $parentEntity,
        string $parentPropertyName,
        EntityInterface $childEntity,
    ): void {
        $parentRepo = OrmManager::getRepository($parentEntity::getRepositoryClass());
        $parentRelation = $parentRepo->getRelation($parentPropertyName);

        if ($parentRelation instanceof HasOne) {
            $parentEntity->setRaw($parentPropertyName, $childEntity);
        }
    }
}
