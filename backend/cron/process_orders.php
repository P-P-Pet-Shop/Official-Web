<?php
declare(strict_types=1);

/**
 * CLI Task: Process and Reconcile Pending Orders
 * Schedule: Every 2-5 minutes
 */

require_once __DIR__ . '/../src/Autoloader.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Cron\CronLock;
use Uber\Adapters\UberExternalOrderAdapter;

Config::load(__DIR__ . '/../.env');

$lockKey = 'cron_process_orders';
if (!CronLock::acquire($lockKey, 180)) {
    echo "[!] process_orders is already running. Skipping.\n";
    exit(0);
}

echo "[*] Reconciling pending orders...\n";
try {
    $db = Database::getInstance()->getConnection();
    
    // Find orders with active external integration that need status sync
    $stmt = $db->query("
        SELECT * FROM orders 
        WHERE uber_order_id IS NOT NULL 
          AND website_order_status IN ('CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP')
        LIMIT 20
    ");
    $orders = $stmt->fetchAll();

    $adapter = new UberExternalOrderAdapter();

    foreach ($orders as $order) {
        $statusData = $adapter->getOrderStatus($order['uber_order_id']);
        if (!empty($statusData['status'])) {
            $newUberStatus = $statusData['status'];
            if ($newUberStatus !== $order['uber_order_status']) {
                $upd = $db->prepare("UPDATE orders SET uber_order_status = :s WHERE id = :id");
                $upd->execute(['s' => $newUberStatus, 'id' => $order['id']]);
                echo "[-] Order #{$order['order_number']} status updated to {$newUberStatus}\n";
            }
        }
    }

    echo "[+] Done reconciling orders.\n";
} catch (Throwable $e) {
    Logger::error("Cron order reconciliation exception: " . $e->getMessage());
    echo "[-] Error: {$e->getMessage()}\n";
} finally {
    CronLock::release($lockKey);
}

