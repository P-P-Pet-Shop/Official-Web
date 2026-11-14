<?php
declare(strict_types=1);

/**
 * CLI Task: Daily System Cleanup
 * Schedule: Once daily at 03:00 AM
 */

require_once __DIR__ . '/../src/Autoloader.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Cron\CronLock;

Config::load(__DIR__ . '/../.env');

$lockKey = 'cron_daily_cleanup';
if (!CronLock::acquire($lockKey, 600)) {
    echo "[!] cleanup is already running. Skipping.\n";
    exit(0);
}

echo "[*] Running daily maintenance cleanup...\n";
try {
    $db = Database::getInstance()->getConnection();

    // 1. Delete abandoned carts older than 14 days
    $cartsDeleted = $db->exec("DELETE FROM carts WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY)");
    echo "[+] Cleaned {$cartsDeleted} expired carts.\n";

    // 2. Prune old API logs older than 30 days
    $logsDeleted = $db->exec("DELETE FROM api_logs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)");
    echo "[+] Pruned {$logsDeleted} old API log entries.\n";

    // 3. Clear expired cron locks
    $locksCleared = $db->exec("DELETE FROM cron_locks WHERE expires_at < UTC_TIMESTAMP()");
    echo "[+] Cleared {$locksCleared} stale cron locks.\n";

    // 4. Prune rate limit cache files older than 2 hours
    $cacheDir = __DIR__ . '/../storage/cache/rate_limits';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*.count');
        $pruned = 0;
        $twoHoursAgo = time() - 7200;
        foreach ($files as $file) {
            if (filemtime($file) < $twoHoursAgo) {
                @unlink($file);
                $pruned++;
            }
        }
        echo "[+] Pruned {$pruned} rate-limit cache files.\n";
    }

    echo "[+] Daily cleanup finished successfully.\n";
} catch (Throwable $e) {
    Logger::error("Daily cleanup exception: " . $e->getMessage());
    echo "[-] Error: {$e->getMessage()}\n";
} finally {
    CronLock::release($lockKey);
}

