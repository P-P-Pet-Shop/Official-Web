<?php
declare(strict_types=1);

namespace Uber;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\UberRepository;

class WebhookService
{
    private UberRepository $repo;

    public function __construct(?UberRepository $repo = null)
    {
        $this->repo = $repo ?? new UberRepository();
    }

    public function verifySignature(string $rawPayload, ?string $signature): bool
    {
        $signingKey = Config::get('uber_webhook_signing_key', '');
        if (empty($signingKey)) {
            Logger::uber('WARNING', "Uber webhook received but no signing key is configured. Rejecting.");
            return false;
        }

        if (empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $signingKey);
        return hash_equals($expected, $signature);
    }

    public function handleEvent(array $payload): array
    {
        $eventId = $payload['event_id'] ?? bin2hex(random_bytes(16));
        $eventType = $payload['event_type'] ?? 'UNKNOWN';

        $recordId = $this->repo->recordWebhookEvent($eventId, $eventType, $payload);
        Logger::uber('INFO', "Received Uber webhook event: {$eventType} [ID: {$eventId}]");

        try {
            switch ($eventType) {
                case 'eats.order.status_update':
                    $this->processOrderStatusUpdate($payload['data'] ?? []);
                    break;

                case 'eats.store.status_update':
                    $this->processStoreStatusUpdate($payload['data'] ?? []);
                    break;

                case 'eats.item.out_of_stock':
                    $this->processItemStockUpdate($payload['data'] ?? []);
                    break;

                default:
                    Logger::uber('INFO', "Unhandled webhook event type: {$eventType}");
                    break;
            }

            $this->repo->updateWebhookStatus($recordId, 'PROCESSED');
            return ['success' => true, 'event_id' => $eventId, 'status' => 'PROCESSED'];

        } catch (\Throwable $e) {
            Logger::uber('ERROR', "Error processing webhook [{$eventId}]: " . $e->getMessage());
            $this->repo->updateWebhookStatus($recordId, 'FAILED', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processOrderStatusUpdate(array $data): void
    {
        $uberOrderId = $data['order_id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$uberOrderId || !$status) {
            return;
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM orders WHERE uber_order_id = :u_id LIMIT 1");
        $stmt->execute(['u_id' => $uberOrderId]);
        $orderId = $stmt->fetchColumn();

        if ($orderId) {
            $upd = $db->prepare("UPDATE orders SET uber_order_status = :status WHERE id = :id");
            $upd->execute(['status' => $status, 'id' => $orderId]);
            Logger::uber('INFO', "Updated local order #{$orderId} Uber status to: {$status}");
        }
    }

    private function processStoreStatusUpdate(array $data): void
    {
        $storeId = $data['store_id'] ?? null;
        $status = $data['status'] ?? null;

        if ($storeId && $status) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE uber_stores SET status = :status, last_synced_at = UTC_TIMESTAMP() WHERE uber_store_id = :id");
            $stmt->execute(['status' => $status, 'id' => $storeId]);
        }
    }

    private function processItemStockUpdate(array $data): void
    {
        $uberItemId = $data['item_id'] ?? null;
        $isAvailable = (bool)($data['is_available'] ?? false);

        if ($uberItemId) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE uber_product_mappings SET uber_is_available = :avail, last_synced_at = UTC_TIMESTAMP() WHERE uber_item_id = :id");
            $stmt->execute(['avail' => $isAvailable ? 1 : 0, 'id' => $uberItemId]);
        }
    }
}

