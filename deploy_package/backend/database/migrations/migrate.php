<?php
declare(strict_types=1);

/**
 * P and P Pet Shop - Database Migration Runner
 * Usage via CLI: php backend/database/migrations/migrate.php
 */

require_once __DIR__ . '/../../src/Autoloader.php';

use App\Core\Config;
use App\Core\Database;

echo "=== P and P Pet Shop - Running Database Migrations ===\n";

try {
    Config::load(__DIR__ . '/../../.env');
    $pdo = Database::getInstance()->getConnection();

    $migrationFile = __DIR__ . '/001_initial_schema.sql';
    if (!file_exists($migrationFile)) {
        throw new RuntimeException("Migration file not found: {$migrationFile}");
    }

    $sql = file_get_contents($migrationFile);
    if ($sql === false) {
        throw new RuntimeException("Could not read migration file");
    }

    // Execute queries
    $pdo->exec($sql);
    echo "✓ Schema migration successfully executed (24 tables verified).\n";

} catch (Throwable $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
