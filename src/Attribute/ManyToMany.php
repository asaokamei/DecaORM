<?php

namespace WScore\DecaORM\Attribute;

use Attribute;

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
     * @param string $foreignKey The foreign key column name in the join table for this entity (e.g., 'student_id')
     * @param string $inverseForeignKey The foreign key column name in the join table for the target entity (e.g., 'course_id')
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
}

