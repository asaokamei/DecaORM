<?php

namespace WScore\DecaORM\Tests\Fixtures\TaskHierarchy;

use PDO;
use WScore\DecaORM\Tests\Support\SchemaLoader;

/**
 * Schema bootstrap for tests that use tasks with optional {@code parent_id} (self-referential hierarchy).
 */
final class TaskHierarchyFixture
{
    public static function loadProjectsAndTasksSchema(PDO $pdo): void
    {
        $pdo->exec(SchemaLoader::loadTable('projects'));
        $pdo->exec(SchemaLoader::loadTable('tasks'));
    }
}
