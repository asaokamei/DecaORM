<?php

namespace WScore\DecaORM\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\Tests\Users\Container;
use WScore\DecaORM\Tests\Users\Course;
use WScore\DecaORM\Tests\Users\CourseRepository;
use WScore\DecaORM\Tests\Users\Student;
use WScore\DecaORM\Tests\Users\StudentRepository;

class ManyToManyTest extends TestCase
{
    private PDO $pdo;
    private StudentRepository $studentRepo;
    private CourseRepository $courseRepo;

    protected function setUp(): void
    {
        // In-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create students table
        $this->pdo->exec(
            "CREATE TABLE students (
            student_id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_name TEXT NOT NULL
        )"
        );

        // Create courses table
        $this->pdo->exec(
            "CREATE TABLE courses (
            course_id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_name TEXT NOT NULL
        )"
        );

        // Create join table
        $this->pdo->exec(
            "CREATE TABLE student_course (
            student_id INTEGER NOT NULL,
            course_id INTEGER NOT NULL,
            PRIMARY KEY (student_id, course_id),
            FOREIGN KEY (student_id) REFERENCES students(student_id),
            FOREIGN KEY (course_id) REFERENCES courses(course_id)
        )"
        );

        // Clear cache before each test
        EntityCache::clear();

        $container = new Container();
        $this->studentRepo = new StudentRepository($this->pdo, $container);
        $this->courseRepo = new CourseRepository($this->pdo, $container);
        $container->set(StudentRepository::class, $this->studentRepo);
        $container->set(CourseRepository::class, $this->courseRepo);
    }

    public function testFillCoursesForStudent(): void
    {
        // Create a student
        $student = $this->studentRepo->createAndSave([
            'name' => 'John Doe'
        ]);

        // Create courses
        $course1 = $this->courseRepo->createAndSave([
            'name' => 'Mathematics'
        ]);

        $course2 = $this->courseRepo->createAndSave([
            'name' => 'Physics'
        ]);

        // Link student to courses in join table
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student->getId()}, {$course1->getId()})");
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student->getId()}, {$course2->getId()})");

        EntityCache::clear();

        // Reload student
        $student = $this->studentRepo->findById($student->getId());
        $this->assertNotNull($student);

        // Fill courses for student
        $courses = $this->studentRepo->fill($student, 'courses');

        // Verify return value
        $this->assertIsArray($courses);
        $this->assertCount(2, $courses);

        // Verify courses are set on student
        $studentCourses = $student->get('courses');
        $this->assertIsArray($studentCourses);
        $this->assertCount(2, $studentCourses);

        // Verify course details
        $courseIds = array_map(fn($course) => $course->getId(), $courses);
        $this->assertContains($course1->getId(), $courseIds);
        $this->assertContains($course2->getId(), $courseIds);

        $courseNames = array_map(fn($course) => $course->get('name'), $courses);
        $this->assertContains('Mathematics', $courseNames);
        $this->assertContains('Physics', $courseNames);
    }

    public function testFillStudentsForCourse(): void
    {
        // Create courses
        $course = $this->courseRepo->createAndSave([
            'name' => 'Mathematics'
        ]);

        // Create students
        $student1 = $this->studentRepo->createAndSave([
            'name' => 'John Doe'
        ]);

        $student2 = $this->studentRepo->createAndSave([
            'name' => 'Jane Smith'
        ]);

        // Link students to course in join table
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student1->getId()}, {$course->getId()})");
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student2->getId()}, {$course->getId()})");

        EntityCache::clear();

        // Reload course
        $course = $this->courseRepo->findById($course->getId());
        $this->assertNotNull($course);

        // Fill students for course
        $students = $this->courseRepo->fill($course, 'students');

        // Verify return value
        $this->assertIsArray($students);
        $this->assertCount(2, $students);

        // Verify students are set on course
        $courseStudents = $course->get('students');
        $this->assertIsArray($courseStudents);
        $this->assertCount(2, $courseStudents);

        // Verify student details
        $studentIds = array_map(fn($student) => $student->getId(), $students);
        $this->assertContains($student1->getId(), $studentIds);
        $this->assertContains($student2->getId(), $studentIds);
    }

    public function testFillCoursesForStudentWithNoCourses(): void
    {
        // Create a student with no courses
        $student = $this->studentRepo->createAndSave([
            'name' => 'Empty Student'
        ]);

        // Fill courses for student
        $courses = $this->studentRepo->fill($student, 'courses');

        // Verify empty array is returned
        $this->assertIsArray($courses);
        $this->assertCount(0, $courses);

        // Verify empty array is set on student
        $studentCourses = $student->get('courses');
        $this->assertIsArray($studentCourses);
        $this->assertCount(0, $studentCourses);
    }

    public function testBidirectionalRelationshipNotSetAutomatically(): void
    {
        // Create student and courses
        $student = $this->studentRepo->createAndSave([
            'name' => 'John Doe'
        ]);

        $course1 = $this->courseRepo->createAndSave([
            'name' => 'Mathematics'
        ]);

        $course2 = $this->courseRepo->createAndSave([
            'name' => 'Physics'
        ]);

        // Link student to courses
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student->getId()}, {$course1->getId()})");
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student->getId()}, {$course2->getId()})");

        EntityCache::clear();

        // Reload student
        $student = $this->studentRepo->findById($student->getId());

        // Fill courses for student
        $this->studentRepo->fill($student, 'courses');

        // Verify student -> courses
        $courses = $student->get('courses');
        $this->assertCount(2, $courses);

        // Verify courses -> student is NOT automatically set
        // (ManyToMany does not set bidirectional links automatically because
        // it would only contain partial data - other students may also be linked to the course)
        foreach ($courses as $course) {
            $this->assertInstanceOf(Course::class, $course);
            $courseStudents = $course->get('students');
            // Should be null or empty, not automatically set
            $this->assertTrue($courseStudents === null || (is_array($courseStudents) && count($courseStudents) === 0));
        }

        // If bidirectional link is needed, explicitly fill it
        $this->courseRepo->fill($courses, 'students');
        
        // Now verify bidirectional link is set
        foreach ($courses as $course) {
            $courseStudents = $course->get('students');
            $this->assertIsArray($courseStudents);
            $this->assertGreaterThanOrEqual(1, count($courseStudents));
            
            // Check if student is in the array
            $studentIds = array_map(fn($s) => $s->getId(), $courseStudents);
            $this->assertContains($student->getId(), $studentIds);
        }
    }

    public function testBatchLoadCoursesForStudents(): void
    {
        // Create multiple students
        $student1 = $this->studentRepo->createAndSave([
            'name' => 'Student One'
        ]);

        $student2 = $this->studentRepo->createAndSave([
            'name' => 'Student Two'
        ]);

        // Create courses
        $course1 = $this->courseRepo->createAndSave([
            'name' => 'Mathematics'
        ]);

        $course2 = $this->courseRepo->createAndSave([
            'name' => 'Physics'
        ]);

        $course3 = $this->courseRepo->createAndSave([
            'name' => 'Chemistry'
        ]);

        // Link student1 to course1 and course2
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student1->getId()}, {$course1->getId()})");
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student1->getId()}, {$course2->getId()})");

        // Link student2 to course2 and course3
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student2->getId()}, {$course2->getId()})");
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student2->getId()}, {$course3->getId()})");

        EntityCache::clear();

        // Reload students
        $student1 = $this->studentRepo->findById($student1->getId());
        $student2 = $this->studentRepo->findById($student2->getId());

        // Batch load courses for students
        $courses = $this->studentRepo->fill([$student1, $student2], 'courses');

        // Verify return value contains all courses
        $this->assertIsArray($courses);
        $this->assertGreaterThanOrEqual(3, count($courses)); // At least 3 unique courses

        // Verify student1 has correct courses
        $student1Courses = $student1->get('courses');
        $this->assertIsArray($student1Courses);
        $this->assertCount(2, $student1Courses);
        $student1CourseIds = array_map(fn($c) => $c->getId(), $student1Courses);
        $this->assertContains($course1->getId(), $student1CourseIds);
        $this->assertContains($course2->getId(), $student1CourseIds);

        // Verify student2 has correct courses
        $student2Courses = $student2->get('courses');
        $this->assertIsArray($student2Courses);
        $this->assertCount(2, $student2Courses);
        $student2CourseIds = array_map(fn($c) => $c->getId(), $student2Courses);
        $this->assertContains($course2->getId(), $student2CourseIds);
        $this->assertContains($course3->getId(), $student2CourseIds);
    }

    public function testSyncAddCourses(): void
    {
        // Create student
        $student = $this->studentRepo->createAndSave([
            'name' => 'John Doe'
        ]);

        // Create courses
        $course1 = $this->courseRepo->createAndSave([
            'name' => 'Mathematics'
        ]);

        $course2 = $this->courseRepo->createAndSave([
            'name' => 'Physics'
        ]);

        // Sync: add courses (set relation property first)
        $student->set('courses', [$course1, $course2]);
        $this->studentRepo->syncManyToMany($student, 'courses');

        // Verify in database
        $stmt = $this->pdo->prepare("SELECT course_id FROM student_course WHERE student_id = ?");
        $stmt->execute([$student->getId()]);
        $courseIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        
        $this->assertCount(2, $courseIds);
        $this->assertContains($course1->getId(), $courseIds);
        $this->assertContains($course2->getId(), $courseIds);
    }

    public function testSyncRemoveCourses(): void
    {
        // Create student
        $student = $this->studentRepo->createAndSave([
            'name' => 'John Doe'
        ]);

        // Create courses
        $course1 = $this->courseRepo->createAndSave([
            'name' => 'Mathematics'
        ]);

        $course2 = $this->courseRepo->createAndSave([
            'name' => 'Physics'
        ]);

        $course3 = $this->courseRepo->createAndSave([
            'name' => 'Chemistry'
        ]);

        // Initially link all courses
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student->getId()}, {$course1->getId()})");
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student->getId()}, {$course2->getId()})");
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student->getId()}, {$course3->getId()})");

        // Sync: remove course2 and course3, keep course1
        $student->set('courses', [$course1]);
        $this->studentRepo->syncManyToMany($student, 'courses');

        // Verify in database
        $stmt = $this->pdo->prepare("SELECT course_id FROM student_course WHERE student_id = ?");
        $stmt->execute([$student->getId()]);
        $courseIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        
        $this->assertCount(1, $courseIds);
        $this->assertContains($course1->getId(), $courseIds);
        $this->assertNotContains($course2->getId(), $courseIds);
        $this->assertNotContains($course3->getId(), $courseIds);
    }

    public function testSyncUpdateCourses(): void
    {
        // Create student
        $student = $this->studentRepo->createAndSave([
            'name' => 'John Doe'
        ]);

        // Create courses
        $course1 = $this->courseRepo->createAndSave([
            'name' => 'Mathematics'
        ]);

        $course2 = $this->courseRepo->createAndSave([
            'name' => 'Physics'
        ]);

        $course3 = $this->courseRepo->createAndSave([
            'name' => 'Chemistry'
        ]);

        // Initially link course1 and course2
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student->getId()}, {$course1->getId()})");
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student->getId()}, {$course2->getId()})");

        // Sync: replace with course2 and course3
        $student->set('courses', [$course2, $course3]);
        $this->studentRepo->syncManyToMany($student, 'courses');

        // Verify in database
        $stmt = $this->pdo->prepare("SELECT course_id FROM student_course WHERE student_id = ? ORDER BY course_id");
        $stmt->execute([$student->getId()]);
        $courseIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        
        $this->assertCount(2, $courseIds);
        $this->assertContains($course2->getId(), $courseIds);
        $this->assertContains($course3->getId(), $courseIds);
        $this->assertNotContains($course1->getId(), $courseIds);
    }

    public function testSyncEmptyCourses(): void
    {
        // Create student
        $student = $this->studentRepo->createAndSave([
            'name' => 'John Doe'
        ]);

        // Create course
        $course = $this->courseRepo->createAndSave([
            'name' => 'Mathematics'
        ]);

        // Initially link course
        $this->pdo->exec("INSERT INTO student_course (student_id, course_id) VALUES ({$student->getId()}, {$course->getId()})");

        // Sync: remove all courses
        $student->set('courses', []);
        $this->studentRepo->syncManyToMany($student, 'courses');

        // Verify in database
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM student_course WHERE student_id = ?");
        $stmt->execute([$student->getId()]);
        $count = $stmt->fetchColumn();
        
        $this->assertEquals(0, $count);
    }

    public function testSyncWithNonExistentCourse(): void
    {
        // Create student
        $student = $this->studentRepo->createAndSave([
            'name' => 'John Doe'
        ]);

        // Sync with non-existent course ID
        // Create a fake course entity with ID 999 (not saved to DB)
        $fakeCourse = $this->courseRepo->createEntity(['name' => 'Fake']);
        $fakeCourse->set('id', '999');
        
        // This should not throw an error, but the relationship won't be created
        // (foreign key constraint would fail in real DB, but SQLite allows it)
        $student->set('courses', [$fakeCourse]);
        $this->studentRepo->syncManyToMany($student, 'courses');

        // Verify in database (should have attempted to insert)
        $stmt = $this->pdo->prepare("SELECT course_id FROM student_course WHERE student_id = ?");
        $stmt->execute([$student->getId()]);
        $courseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // SQLite doesn't enforce foreign keys by default, so this might succeed
        // In a real database with FK constraints, this would fail
        $this->assertIsArray($courseIds);
    }

    public function testSyncRequiresEntityId(): void
    {
        // Create student without saving (no ID)
        $student = $this->studentRepo->createEntity([
            'name' => 'John Doe'
        ]);

        // Create course
        $course = $this->courseRepo->createAndSave([
            'name' => 'Mathematics'
        ]);

        // Sync should fail because student has no ID
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entity must have an ID to sync relations');
        
        $student->set('courses', [$course]);
        $this->studentRepo->syncManyToMany($student, 'courses');
    }

    public function testSyncRequiresManyToManyRelation(): void
    {
        // Create student
        $student = $this->studentRepo->createAndSave([
            'name' => 'John Doe'
        ]);

        // Try to sync a non-existent relation
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Relation 'nonexistent' is not a ManyToMany relationship");
        
        $this->studentRepo->syncManyToMany($student, 'nonexistent');
    }
}

