<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UberRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getActiveStore(): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM uber_stores WHERE status = 'ACTIVE' LIMIT 1");
        $stmt->execute();
        $store = $stmt->fetch();
        return $store ?: null;
    }

    public function saveStore(string $storeId, string $name, string $status = 'ACTIVE', ?string $address = null, ?array $rawData = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO uber_stores (uber_store_id, store_name, status, address, raw_store_data, last_synced_at)
            VALUES (:store_id, :name, :status, :address, :raw, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE 
                store_name = VALUES(store_name),
                status = VALUES(status),
                address = VALUES(address),
                raw_store_data = VALUES(raw_store_data),
                last_synced_at = VALUES(last_synced_at)
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'name' => $name,
            'status' => $status,
            'address' => $address,
            'raw' => $rawData ? json_encode($rawData) : null,
        ]);
    }

    public function getMappingByProductId(int $productId, ?int $variantId = null): ?array
    {
        $sql = "SELECT * FROM uber_product_mappings WHERE product_id = :p_id";
        $params = ['p_id' => $productId];

        if ($variantId !== null) {
            $sql .= " AND variant_id = :v_id";
            $params['v_id'] = $variantId;
        } else {
            $sql .= " AND variant_id IS NULL";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function saveMapping(int $productId, ?int $variantId, string $uberItemId, float $price, bool $isAvailable, string $syncStatus = 'SYNCED', ?string $error = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO uber_product_mappings (product_id, variant_id, uber_item_id, uber_price, uber_is_available, sync_status, sync_error, last_synced_at)
            VALUES (:p_id, :v_id, :uber_id, :price, :avail, :status, :err, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
                uber_price = VALUES(uber_price),
                uber_is_available = VALUES(uber_is_available),
                sync_status = VALUES(sync_status),
                sync_error = VALUES(sync_error),
                last_synced_at = VALUES(last_synced_at)
        ");
        $stmt->execute([
            'p_id' => $productId,
            'v_id' => $variantId,
            'uber_id' => $uberItemId,
            'price' => $price,
            'avail' => $isAvailable ? 1 : 0,
            'status' => $syncStatus,
            'err' => $error,
        ]);
    }

    public function startSyncLog(string $syncType): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO uber_sync_logs (sync_type, status, items_processed, items_updated, items_failed, started_at)
            VALUES (:type, 'STARTED', 0, 0, 0, UTC_TIMESTAMP())
        ");
        $stmt->execute(['type' => $syncType]);
        return (int)$this->db->lastInsertId();
    }

    public function finishSyncLog(int $logId, string $status, int $processed, int $updated, int $failed, ?string $errorDetails = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE uber_sync_logs 
            SET status = :status,
                items_processed = :processed,
                items_updated = :updated,
                items_failed = :failed,
                error_details = :err,
                completed_at = UTC_TIMESTAMP()
            WHERE id = :id
        ");
        $stmt->execute([
            'status' => $status,
            'processed' => $processed,
            'updated' => $updated,
            'failed' => $failed,
            'err' => $errorDetails,
            'id' => $logId,
        ]);
    }

    public function getRecentSyncLogs(int $limit = 20): array
    {
        $stmt = $this->db->prepare("SELECT * FROM uber_sync_logs ORDER BY started_at DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllMappings(int $limit = 100): array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, p.name as product_name, p.sku as product_sku, p.base_price as current_site_price,
                   v.variant_name
            FROM uber_product_mappings m
            JOIN products p ON m.product_id = p.id
            LEFT JOIN product_variants v ON m.variant_id = v.id
            ORDER BY m.last_synced_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function recordWebhookEvent(string $eventId, string $eventType, array $payload): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO uber_webhook_events (event_id, event_type, payload, status)
            VALUES (:id, :type, :payload, 'RECEIVED')
        ");
        $stmt->execute([
            'id' => $eventId,
            'type' => $eventType,
            'payload' => json_encode($payload),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateWebhookStatus(int $id, string $status, ?string $error = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE uber_webhook_events 
            SET status = :status, error_message = :err, processed_at = UTC_TIMESTAMP()
            WHERE id = :id
        ");
        $stmt->execute([
            'status' => $status,
            'err' => $error,
            'id' => $id,
        ]);
    }
}

