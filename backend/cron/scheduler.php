<?php
declare(strict_types=1);

/**
 * P and P Pet Shop - Master Cron Scheduler
 * Single cPanel Cron Entry:
 * * * * * * php /home/username/backend/cron/scheduler.php > /dev/null 2>&1
 */

require_once __DIR__ . '/../src/Autoloader.php';

use App\Core\Config;
use App\Core\Logger;

Config::load(__DIR__ . '/../.env');

$minute = (int)date('i');
$hour = (int)date('H');

echo "=== P&P Pet Shop Cron Scheduler [UTC: " . gmdate('Y-m-d H:i:s') . "] ===\n";

$phpBinary = PHP_BINARY ?: 'php';

// 1. Process orders: Every 2 minutes
if ($minute % 2 === 0) {
    echo "-> Running process_orders.php...\n";
    passthru("{$phpBinary} " . escapeshellarg(__DIR__ . '/process_orders.php'));
}

// 2. Inventory sync: Every 10 minutes
if ($minute % 10 === 0) {
    echo "-> Running sync_inventory.php...\n";
    passthru("{$phpBinary} " . escapeshellarg(__DIR__ . '/sync_inventory.php'));
}

// 3. Price sync: Every 15 minutes
if ($minute % 15 === 0) {
    echo "-> Running sync_prices.php...\n";
    passthru("{$phpBinary} " . escapeshellarg(__DIR__ . '/sync_prices.php'));
}

// 4. Product catalog sync: Every 30 minutes
if ($minute % 30 === 0) {
    echo "-> Running sync_products.php...\n";
    passthru("{$phpBinary} " . escapeshellarg(__DIR__ . '/sync_products.php'));
}

// 5. Daily cleanup: Every day at 03:00 AM
if ($hour === 3 && $minute === 0) {
    echo "-> Running cleanup.php...\n";
    passthru("{$phpBinary} " . escapeshellarg(__DIR__ . '/cleanup.php'));
}

echo "=== Scheduler Run Complete ===\n";

