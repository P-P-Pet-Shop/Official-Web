<?php
declare(strict_types=1);

/**
 * CLI Task: Sync Uber Products
 * Schedule: Every 30 minutes
 */

require_once __DIR__ . '/../src/Autoloader.php';

use App\Core\Config;
use App\Core\Logger;
use App\Cron\CronLock;
use Uber\ProductSyncService;

Config::load(__DIR__ . '/../.env');

$lockKey = 'cron_sync_products';
if (!CronLock::acquire($lockKey, 600)) {
    echo "[!] sync_products is already running or locked. Skipping.\n";
    exit(0);
}

echo "[*] Starting product catalog synchronization with Uber Eats...\n";
try {
    $service = new ProductSyncService();
    $result = $service->syncAllProducts();
    echo "[+] Done: {$result['message']}\n";
} catch (Throwable $e) {
    Logger::uber('ERROR', "Cron product sync exception: " . $e->getMessage());
    echo "[-] Error: {$e->getMessage()}\n";
} finally {
    CronLock::release($lockKey);
}

