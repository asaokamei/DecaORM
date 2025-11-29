<?php

require_once __DIR__ . '/../vendor/autoload.php';

use WScore\DecaORM\Tests\PerformanceBenchmark;

/**
 * Opcache有効/無効でのパフォーマンス比較
 */
class PerformanceComparison
{
    public function compareWithOpcache(): void
    {
        echo "=== Opcache Performance Comparison ===\n\n";

        $benchmark = new PerformanceBenchmark();

        // Opcache有効
        echo "--- With Opcache Enabled ---\n";
        echo "Command: php tests/PerformanceBenchmark.php\n";
        $this->runBenchmark($benchmark, true);

        echo "\n";

        // Opcache無効
        echo "--- With Opcache Disabled ---\n";
        echo "Command: php -d opcache.enable_cli=0 tests/PerformanceBenchmark.php\n";
        $this->runBenchmark($benchmark, false);
    }

    private function runBenchmark(PerformanceBenchmark $benchmark, bool $withOpcache): void
    {
        // Opcacheの状態を変更
        if (!$withOpcache) {
            ini_set('opcache.enable_cli', '0');
        }

        $opcacheStatus = opcache_get_status();
        $opcacheEnabled = $opcacheStatus !== false;
        echo "Opcache enabled: " . ($opcacheEnabled ? "Yes" : "No") . "\n";

        // ウォームアップ（Opcacheを有効にするため）
        if ($withOpcache) {
            new \WScore\DecaORM\AttributeHydrator(\WScore\DecaORM\Tests\Users\UserWithAttribute::class);
            new \WScore\DecaORM\Tests\Users\UserHydrator();
        }

        echo "\n--- Hydrator Creation ---\n";
        $creationResults = $benchmark->benchmarkHydratorCreation();
        $this->printResults($creationResults);

        echo "\n--- Hydrate Operation ---\n";
        $hydrateResults = $benchmark->benchmarkHydrate();
        $this->printResults($hydrateResults);

        echo "\n--- Dehydrate Operation ---\n";
        $dehydrateResults = $benchmark->benchmarkDehydrate();
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

// コマンドラインから実行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $comparison = new PerformanceComparison();
    $comparison->compareWithOpcache();
}

