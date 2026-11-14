<?php
declare(strict_types=1);

/**
 * CLI Task: Sync Uber Inventory & Availability
 * Schedule: Every 5-10 minutes
 */

require_once __DIR__ . '/../src/Autoloader.php';

use App\Core\Config;
use App\Core\Logger;
use App\Cron\CronLock;
use Uber\InventorySyncService;

Config::load(__DIR__ . '/../.env');

$lockKey = 'cron_sync_inventory';
if (!CronLock::acquire($lockKey, 300)) {
    echo "[!] sync_inventory is already running. Skipping.\n";
    exit(0);
}

echo "[*] Synchronizing inventory availability with Uber Eats...\n";
try {
    $service = new InventorySyncService();
    $result = $service->syncInventory();
    echo "[+] Done. Processed: " . ($result['processed'] ?? 0) . ", Updated: " . ($result['updated'] ?? 0) . "\n";
} catch (Throwable $e) {
    Logger::uber('ERROR', "Cron inventory sync exception: " . $e->getMessage());
    echo "[-] Error: {$e->getMessage()}\n";
} finally {
    CronLock::release($lockKey);
}

