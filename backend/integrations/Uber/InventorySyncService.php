<?php
declare(strict_types=1);

namespace Uber;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\UberRepository;
use PDO;

class InventorySyncService
{
    private UberClient $client;
    private UberRepository $repo;
    private PDO $db;

    public function __construct(?UberClient $client = null, ?UberRepository $repo = null)
    {
        $this->client = $client ?? new UberClient();
        $this->repo = $repo ?? new UberRepository();
        $this->db = Database::getInstance()->getConnection();
    }

    public function syncPrices(): array
    {
        $logId = $this->repo->startSyncLog('PRICE');
        $processed = 0;
        $updated = 0;
        $failed = 0;

        try {
            // Get all mapped products
            $stmt = $this->db->prepare("
                SELECT m.*, p.base_price, p.sale_price 
                FROM uber_product_mappings m
                JOIN products p ON m.product_id = p.id
                WHERE m.sync_status = 'SYNCED'
            ");
            $stmt->execute();
            $mappings = $stmt->fetchAll();

            foreach ($mappings as $row) {
                $processed++;
                $uberPrice = (float)$row['uber_price'];
                $websitePrice = (float)($row['sale_price'] ?? $row['base_price']);

                // If Uber is designated as authority or price drifted
                if (abs($uberPrice - $websitePrice) > 0.01 && $uberPrice > 0) {
                    $upd = $this->db->prepare("UPDATE products SET sale_price = :p WHERE id = :id");
                    $upd->execute(['p' => $uberPrice, 'id' => $row['product_id']]);
                    $updated++;
                }
            }

            $this->repo->finishSyncLog($logId, 'SUCCESS', $processed, $updated, $failed);
            return ['success' => true, 'processed' => $processed, 'updated' => $updated];
        } catch (\Throwable $e) {
            $this->repo->finishSyncLog($logId, 'FAILED', $processed, $updated, $failed, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncInventory(): array
    {
        $logId = $this->repo->startSyncLog('INVENTORY');
        $processed = 0;
        $updated = 0;
        $failed = 0;

        try {
            $stmt = $this->db->prepare("
                SELECT m.*, 
                       COALESCE((SELECT SUM(quantity_on_hand - quantity_reserved) FROM inventory WHERE product_id = m.product_id), 0) as stock
                FROM uber_product_mappings m
                WHERE m.sync_status = 'SYNCED'
            ");
            $stmt->execute();
            $mappings = $stmt->fetchAll();

            $storeId = Config::get('uber_store_id', '');

            foreach ($mappings as $row) {
                $processed++;
                $isAvailable = (int)$row['stock'] > 0;
                $uberAvailable = (bool)$row['uber_is_available'];

                if ($isAvailable !== $uberAvailable) {
                    // Update local mapping record
                    $upd = $this->db->prepare("UPDATE uber_product_mappings SET uber_is_available = :avail WHERE id = :id");
                    $upd->execute(['avail' => $isAvailable ? 1 : 0, 'id' => $row['id']]);

                    // If store ID and client are connected, send availability update to Uber API
                    if (!empty($storeId)) {
                        $this->client->post("/v1/eats/stores/{$storeId}/items/{$row['uber_item_id']}", [
                            'suspension' => $isAvailable ? null : ['reason' => 'OUT_OF_STOCK']
                        ]);
                    }

                    $updated++;
                }
            }

            $this->repo->finishSyncLog($logId, 'SUCCESS', $processed, $updated, $failed);
            return ['success' => true, 'processed' => $processed, 'updated' => $updated];
        } catch (\Throwable $e) {
            $this->repo->finishSyncLog($logId, 'FAILED', $processed, $updated, $failed, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

