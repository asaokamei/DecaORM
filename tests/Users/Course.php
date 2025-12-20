<?php

namespace WScore\DecaORM\Tests\Users;

use WScore\DecaORM\Attribute\Column;
use WScore\DecaORM\Attribute\GeneratedValue;
use WScore\DecaORM\Attribute\Id;
use WScore\DecaORM\Attribute\ManyToMany;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Attribute\Table;
use WScore\DecaORM\EntityInterface;
use WScore\DecaORM\EntityTrait;

#[Table(name: 'courses')]
#[Repository(CourseRepository::class)]
class Course implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'course_id')]
    public ?string $id = null;

    #[Column(name: 'course_name')]
    public string $name = '';

    /** @var Student[]|null */
    #[ManyToMany(
        targetEntity: Student::class,
        joinTable: 'student_course',
        foreignKey: 'course_id',
        inverseForeignKey: 'student_id'
    )]
    public ?array $students = null;

    public function getId(): ?int
    {
        return $this->id !== null ? (int) $this->id : null;
    }
}

