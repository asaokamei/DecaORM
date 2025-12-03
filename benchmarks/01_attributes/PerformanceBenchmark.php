<?php

namespace WScore\DecaORM\Tests;

use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\Tests\Users\User;
use WScore\DecaORM\Tests\Users\UserHydrator;
use WScore\DecaORM\Tests\Users\UserWithAttribute;

/**
 * AttributeHydratorと手動Hydratorのパフォーマンス比較
 */
class PerformanceBenchmark
{
    private const ITERATIONS = 10000;

    public function benchmarkHydratorCreation(): array
    {
        $results = [];

        // AttributeHydratorのインスタンス化（リフレクション実行）
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $hydrator = new AttributeHydrator(UserWithAttribute::class);
        }
        $end = microtime(true);
        $attributeTime = ($end - $start) * 1000; // ミリ秒
        $results['AttributeHydrator creation'] = [
            'total_ms' => $attributeTime,
            'per_instance_us' => ($attributeTime / self::ITERATIONS) * 1000, // マイクロ秒
        ];

        // 手動Hydratorのインスタンス化
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $hydrator = new UserHydrator();
        }
        $end = microtime(true);
        $manualTime = ($end - $start) * 1000; // ミリ秒
        $results['Manual Hydrator creation'] = [
            'total_ms' => $manualTime,
            'per_instance_us' => ($manualTime / self::ITERATIONS) * 1000, // マイクロ秒
        ];

        $results['Overhead'] = [
            'absolute_us' => (($attributeTime - $manualTime) / self::ITERATIONS) * 1000,
            'relative' => ($attributeTime / $manualTime) . 'x',
        ];

        return $results;
    }

    public function benchmarkHydrate(): array
    {
        $results = [];

        $attributeHydrator = new AttributeHydrator(UserWithAttribute::class);
        $manualHydrator = new UserHydrator();

        $data = [
            'user_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        // AttributeHydratorでのhydrate
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $entity = $attributeHydrator->hydrate($data);
        }
        $end = microtime(true);
        $attributeTime = ($end - $start) * 1000;
        $results['AttributeHydrator hydrate'] = [
            'total_ms' => $attributeTime,
            'per_call_us' => ($attributeTime / self::ITERATIONS) * 1000,
        ];

        // 手動Hydratorでのhydrate
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $entity = $manualHydrator->hydrate($data);
        }
        $end = microtime(true);
        $manualTime = ($end - $start) * 1000;
        $results['Manual Hydrator hydrate'] = [
            'total_ms' => $manualTime,
            'per_call_us' => ($manualTime / self::ITERATIONS) * 1000,
        ];

        $results['Overhead'] = [
            'absolute_us' => (($attributeTime - $manualTime) / self::ITERATIONS) * 1000,
            'relative' => ($attributeTime / $manualTime) . 'x',
        ];

        return $results;
    }

    public function benchmarkDehydrate(): array
    {
        $results = [];

        $attributeHydrator = new AttributeHydrator(UserWithAttribute::class);
        $manualHydrator = new UserHydrator();

        $attributeEntity = new UserWithAttribute();
        $attributeEntity->setId(1);
        $attributeEntity->set('name', 'Test User');
        $attributeEntity->set('email', 'test@example.com');

        $manualEntity = new User();
        $manualEntity->setId(1);
        $manualEntity->set('name', 'Test User');
        $manualEntity->set('email', 'test@example.com');

        // AttributeHydratorでのdehydrate
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $data = $attributeHydrator->dehydrate($attributeEntity);
        }
        $end = microtime(true);
        $attributeTime = ($end - $start) * 1000;
        $results['AttributeHydrator dehydrate'] = [
            'total_ms' => $attributeTime,
            'per_call_us' => ($attributeTime / self::ITERATIONS) * 1000,
        ];

        // 手動Hydratorでのdehydrate
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $data = $manualHydrator->dehydrate($manualEntity);
        }
        $end = microtime(true);
        $manualTime = ($end - $start) * 1000;
        $results['Manual Hydrator dehydrate'] = [
            'total_ms' => $manualTime,
            'per_call_us' => ($manualTime / self::ITERATIONS) * 1000,
        ];

        $results['Overhead'] = [
            'absolute_us' => (($attributeTime - $manualTime) / self::ITERATIONS) * 1000,
            'relative' => ($attributeTime / $manualTime) . 'x',
        ];

        return $results;
    }

    public function runAll(): void
    {
        echo "=== Performance Benchmark ===\n";
        echo "Iterations: " . number_format(self::ITERATIONS) . "\n";
        
        // Opcacheの状態を表示
        $opcacheStatus = opcache_get_status();
        $opcacheEnabled = $opcacheStatus !== false;
        echo "Opcache enabled: " . ($opcacheEnabled ? "Yes" : "No") . "\n";
        if ($opcacheEnabled) {
            echo "Opcache memory used: " . number_format($opcacheStatus['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
            echo "Opcache cached scripts: " . number_format($opcacheStatus['opcache_statistics']['num_cached_scripts']) . "\n";
        }
        echo "\n";

        echo "--- Hydrator Creation ---\n";
        $creationResults = $this->benchmarkHydratorCreation();
        $this->printResults($creationResults);
        echo "\n";

        echo "--- Hydrate Operation ---\n";
        $hydrateResults = $this->benchmarkHydrate();
        $this->printResults($hydrateResults);
        echo "\n";

        echo "--- Dehydrate Operation ---\n";
        $dehydrateResults = $this->benchmarkDehydrate();
        $this->printResults($dehydrateResults);
    }

    private function printResults(array $results): void
    {
        foreach ($results as $key => $value) {
            if ($key === 'Overhead') {
                echo sprintf(
                    "  %s: %s us (absolute), %s (relative)\n",
                    $key,
                    number_format($value['absolute_us'], 2),
                    $value['relative']
                );
            } else {
                if (isset($value['per_instance_us'])) {
                    echo sprintf(
                        "  %s: %.2f ms total, %.2f us per instance\n",
                        $key,
                        $value['total_ms'],
                        $value['per_instance_us']
                    );
                } else {
                    echo sprintf(
                        "  %s: %.2f ms total, %.2f us per call\n",
                        $key,
                        $value['total_ms'],
                        $value['per_call_us']
                    );
                }
            }
        }
    }
}

// コマンドラインから実行可能にする
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $benchmark = new PerformanceBenchmark();
    $benchmark->runAll();
}

