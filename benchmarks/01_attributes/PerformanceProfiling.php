<?php

require_once __DIR__ . '/../vendor/autoload.php';

use WScore\DecaORM\Tests\Users\User;
use WScore\DecaORM\Tests\Users\UserWithAttribute;

/**
 * Hydrate/Dehydrateの詳細なプロファイリング
 */
class PerformanceProfiling
{
    private const ITERATIONS = 10000;

    public function profileEntityTraitGetSet(): void
    {
        echo "=== EntityTrait get/set プロファイリング ===\n";
        echo "Iterations: " . number_format(self::ITERATIONS) . "\n\n";

        $entity = new User();
        $entity->setId(1);
        $entity->set('name', 'Test User');
        $entity->set('email', 'test@example.com');

        // get()のプロファイリング
        echo "--- get() method ---\n";
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $name = $entity->get('name');
            $email = $entity->get('email');
            $id = $entity->get('user_id');
        }
        $end = microtime(true);
        $getTime = ($end - $start) * 1000;
        echo sprintf("Total: %.2f ms, Per call: %.2f us\n", $getTime, ($getTime / (self::ITERATIONS * 3)) * 1000);

        // set()のプロファイリング
        echo "\n--- set() method ---\n";
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $entity->set('name', 'Test User');
            $entity->set('email', 'test@example.com');
            $entity->set('user_id', 1);
        }
        $end = microtime(true);
        $setTime = ($end - $start) * 1000;
        echo sprintf("Total: %.2f ms, Per call: %.2f us\n", $setTime, ($setTime / (self::ITERATIONS * 3)) * 1000);

        // 直接プロパティアクセスの比較
        echo "\n--- Direct property access (for comparison) ---\n";
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $name = $entity->name ?? null;
            $email = $entity->email ?? null;
            $id = $entity->user_id ?? null;
        }
        $end = microtime(true);
        $directTime = ($end - $start) * 1000;
        echo sprintf("Total: %.2f ms, Per call: %.2f us\n", $directTime, ($directTime / (self::ITERATIONS * 3)) * 1000);
        echo sprintf("Overhead: %.2f us per call (%.1fx slower)\n", 
            (($getTime / (self::ITERATIONS * 3)) - ($directTime / (self::ITERATIONS * 3))) * 1000,
            ($getTime / $directTime)
        );
    }

    public function profileMethodExists(): void
    {
        echo "\n=== method_exists() コスト測定 ===\n";
        echo "Iterations: " . number_format(self::ITERATIONS) . "\n\n";

        $entity = new User();

        // method_exists()のコスト
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            method_exists($entity, 'getName');
            method_exists($entity, 'getEmail');
            method_exists($entity, 'getUser_id');
            method_exists($entity, 'getUserId');
        }
        $end = microtime(true);
        $methodExistsTime = ($end - $start) * 1000;
        echo sprintf("method_exists() x4: %.2f ms, Per call: %.2f us\n", 
            $methodExistsTime, 
            ($methodExistsTime / (self::ITERATIONS * 4)) * 1000
        );

        // property_exists()のコスト
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            property_exists($entity, 'name');
            property_exists($entity, 'email');
            property_exists($entity, 'user_id');
        }
        $end = microtime(true);
        $propertyExistsTime = ($end - $start) * 1000;
        echo sprintf("property_exists() x3: %.2f ms, Per call: %.2f us\n", 
            $propertyExistsTime, 
            ($propertyExistsTime / (self::ITERATIONS * 3)) * 1000
        );
    }

    public function profileStringOperations(): void
    {
        echo "\n=== 文字列操作のコスト測定 ===\n";
        echo "Iterations: " . number_format(self::ITERATIONS) . "\n\n";

        $name = 'name';
        $email = 'email';
        $user_id = 'user_id';

        // ucfirst()のコスト
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            ucfirst($name);
            ucfirst($email);
            ucfirst($user_id);
        }
        $end = microtime(true);
        $ucfirstTime = ($end - $start) * 1000;
        echo sprintf("ucfirst() x3: %.2f ms, Per call: %.2f us\n", 
            $ucfirstTime, 
            ($ucfirstTime / (self::ITERATIONS * 3)) * 1000
        );

        // str_replace()のコスト
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            str_replace('_', '', $user_id);
            str_replace('_', '', 'created_at');
        }
        $end = microtime(true);
        $strReplaceTime = ($end - $start) * 1000;
        echo sprintf("str_replace() x2: %.2f ms, Per call: %.2f us\n", 
            $strReplaceTime, 
            ($strReplaceTime / (self::ITERATIONS * 2)) * 1000
        );
    }

    public function profileHydrateBreakdown(): void
    {
        echo "\n=== Hydrate処理の内訳 ===\n";
        echo "Iterations: " . number_format(self::ITERATIONS) . "\n\n";

        $entity = new User();
        $data = [
            'user_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        $properties = ['user_id', 'name', 'email', 'created_at', 'updated_at'];

        // ループとissetチェックのみ
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            foreach ($properties as $property) {
                if (isset($data[$property])) {
                    // 何もしない
                }
            }
        }
        $end = microtime(true);
        $loopTime = ($end - $start) * 1000;
        echo sprintf("Loop + isset() only: %.2f ms, Per iteration: %.2f us\n", 
            $loopTime, 
            ($loopTime / self::ITERATIONS) * 1000
        );

        // set()呼び出しを含む
        $start = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            foreach ($properties as $property) {
                if (isset($data[$property])) {
                    $entity->set($property, $data[$property]);
                }
            }
        }
        $end = microtime(true);
        $setTime = ($end - $start) * 1000;
        echo sprintf("Loop + isset() + set(): %.2f ms, Per iteration: %.2f us\n", 
            $setTime, 
            ($setTime / self::ITERATIONS) * 1000
        );
        echo sprintf("set() overhead: %.2f us per iteration\n", 
            (($setTime - $loopTime) / self::ITERATIONS) * 1000
        );
    }

    public function runAll(): void
    {
        $this->profileEntityTraitGetSet();
        $this->profileMethodExists();
        $this->profileStringOperations();
        $this->profileHydrateBreakdown();
    }
}

// コマンドラインから実行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $profiling = new PerformanceProfiling();
    $profiling->runAll();
}

