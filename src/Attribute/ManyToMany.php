<?php

namespace WScore\DecaORM\Attribute;

use Attribute;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\Contracts\RepositoryInterface;
use WScore\DecaORM\EntityCollection;

/**
 * ManyToMany relationship attribute
 * 
 * Used for many-to-many relationships through a join table.
 * Neither entity holds a foreign key; the join table holds both foreign keys.
 * 
 * Example:
 * ```php
 * #[ManyToMany(
 *     targetEntity: Course::class,
 *     joinTable: 'student_course',
 *     foreignKey: 'student_id',
 *     inverseForeignKey: 'course_id'
 * )]
 * public ?array $courses = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany
{
    /** @var string The property name on the attribute's entity */
    public string $propertyName = '';

    /**
     * @param string $targetEntity The target entity class name (e.g., Course::class)
     * @param string $joinTable The join table name (e.g., 'student_course')
     * @param string $foreignKey The foreign key *column name* in the join table for this entity (e.g., 'student_id')
     * @param string $inverseForeignKey The foreign key *column name* in the join table for the target entity (e.g., 'course_id')
     * @param string|null $orderBy Optional ORDER BY clause (e.g., 'created_at DESC')
     */
    public function __construct(
        public string $targetEntity,
        public string $joinTable,
        public string $foreignKey,
        public string $inverseForeignKey,
        public ?string $orderBy = null
    ) {
    }

    /**
     * Sets the in-memory ManyToMany collection on the owning entity (or null).
     * Does not write the join table; inverse side is not updated here.
     *
     * @param iterable<EntityInterface>|EntityCollection|null $targets
     */
    public function associate(RepositoryInterface $ownerRepo, EntityInterface $entity, iterable|EntityCollection|null $targets): void
    {
        if ($targets === null) {
            $entity->setRaw($this->propertyName, null);
            return;
        }

        $targetRepo = $this->targetEntity ? $ownerRepo->getRepository($this->targetEntity) : null;

        $list = [];
        if ($targets instanceof EntityCollection) {
            foreach ($targets as $t) {
                if ($t instanceof EntityInterface) {
                    $list[] = $t;
                }
            }
        } else {
            foreach ($targets as $t) {
                if ($t instanceof EntityInterface) {
                    $list[] = $t;
                }
            }
        }

        $seenRef = [];
        $seenId = [];
        $deduped = [];
        foreach ($list as $t) {
            $id = $t->getId();
            if ($id !== null) {
                $key = (string) $id;
                if (isset($seenId[$key])) {
                    continue;
                }
                $seenId[$key] = true;
            } else {
                $hash = spl_object_hash($t);
                if (isset($seenRef[$hash])) {
                    continue;
                }
                $seenRef[$hash] = true;
            }
            $deduped[] = $t;
        }

        $entity->setRaw($this->propertyName, new EntityCollection($deduped, $targetRepo));
    }
}

