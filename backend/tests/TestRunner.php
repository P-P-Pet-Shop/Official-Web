<?php
declare(strict_types=1);

/**
 * P and P Pet Shop - Standalone Test Runner
 * Usage: php backend/tests/TestRunner.php
 */

require_once __DIR__ . '/../src/Autoloader.php';

use App\Core\Config;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function assert(bool $condition, string $testName, string $message = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  ✓ {$testName}\n";
        } else {
            $this->failed++;
            $msg = empty($message) ? "Assertion failed" : $message;
            $this->failures[] = "{$testName}: {$msg}";
            echo "  ✗ {$testName} - {$msg}\n";
        }
    }

    public function run(): void
    {
        echo "====================================================\n";
        echo " P and P Pet Shop - Automated Test Suite\n";
        echo " Environment: PHP " . PHP_VERSION . "\n";
        echo "====================================================\n\n";

        $testFiles = glob(__DIR__ . '/*/*Test.php') ?: [];
        sort($testFiles);

        foreach ($testFiles as $file) {
            $className = basename($file, '.php');
            echo "Running {$className}...\n";
            require_once $file;
            $testInstance = new $className($this);
            $testInstance->execute();
            echo "\n";
        }

        echo "====================================================\n";
        echo " Total Tests: " . ($this->passed + $this->failed) . "\n";
        echo " Passed: {$this->passed}\n";
        echo " Failed: {$this->failed}\n";
        echo "====================================================\n";

        if ($this->failed > 0) {
            echo "\nFailures Summary:\n";
            foreach ($this->failures as $f) {
                echo " - {$f}\n";
            }
            exit(1);
        } else {
            echo "\n🎉 All tests passed successfully!\n";
            exit(0);
        }
    }
}

$runner = new TestRunner();
$runner->run();

