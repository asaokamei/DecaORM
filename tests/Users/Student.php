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

#[Table(name: 'students')]
#[Repository(StudentRepository::class)]
class Student implements EntityInterface
{
    use EntityTrait;

    #[Id]
    #[GeneratedValue]
    #[Column(name: 'student_id')]
    public ?string $id = null;

    #[Column(name: 'student_name')]
    public string $name = '';

    /** @var Course[]|null */
    #[ManyToMany(
        targetEntity: Course::class,
        joinTable: 'student_course',
        foreignKey: 'student_id',
        inverseForeignKey: 'course_id'
    )]
    public ?array $courses = null;

    public function getId(): ?int
    {
        return $this->id !== null ? (int) $this->id : null;
    }
}

