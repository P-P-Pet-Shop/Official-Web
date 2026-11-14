<?php
declare(strict_types=1);

namespace Uber\Adapters;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use Uber\UberClient;

class UberExternalOrderAdapter implements UberOrderAdapterInterface
{
    private UberClient $client;

    public function __construct(?UberClient $client = null)
    {
        $this->client = $client ?? new UberClient();
    }

    public function isSupportedAndApproved(): bool
    {
        // Must have Uber credentials and explicit authorization flag enabled
        $hasCredentials = !empty(Config::get('uber_client_id')) && !empty(Config::get('uber_store_id'));
        $isExternalOrderInjectionApproved = (bool)Config::get('uber_external_order_injection_approved', false);

        return $hasCredentials && $isExternalOrderInjectionApproved;
    }

    public function submitOrder(array $order): array
    {
        if (!$this->isSupportedAndApproved()) {
            Logger::uber('INFO', "Uber External Order Injection is not approved for this merchant account. Order #{$order['order_number']} saved for local fulfillment.");
            
            // Update order status to AWAITING_MANUAL_FULFILLMENT without claiming Uber acceptance
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE orders SET uber_order_status = 'NOT_INTEGRATED_LOCAL_ONLY' WHERE id = :id");
            $stmt->execute(['id' => $order['id']]);

            return [
                'success' => false,
                'status' => 'NOT_APPROVED',
                'message' => 'Uber Eats external order creation is pending merchant API approval. Order preserved for local fulfillment.',
            ];
        }

        // When approved by Uber Partner Program for POS order injection:
        $storeId = Config::get('uber_store_id');
        $payload = [
            'external_id' => $order['order_number'],
            'customer' => [
                'name' => $order['customer_name'],
                'phone' => $order['customer_phone'],
            ],
            'delivery_address' => [
                'street_address' => [$order['delivery_address']],
                'city' => $order['delivery_city'],
            ],
            'items' => array_map(function ($item) {
                return [
                    'id' => $item['sku'] ?? (string)$item['product_id'],
                    'title' => $item['product_name'],
                    'quantity' => (int)$item['quantity'],
                    'price' => (int)round(((float)$item['unit_price']) * 100), // cents/subunits
                ];
            }, $order['items'] ?? []),
        ];

        $res = $this->client->post("/v1/eats/stores/{$storeId}/orders", $payload);

        if ($res['success'] && !empty($res['data']['order_id'])) {
            $uberOrderId = $res['data']['order_id'];
            $db = Database::getInstance()->getConnection();
            $upd = $db->prepare("UPDATE orders SET uber_order_id = :u_id, uber_order_status = 'SUBMITTED_TO_UBER' WHERE id = :id");
            $upd->execute(['u_id' => $uberOrderId, 'id' => $order['id']]);

            return [
                'success' => true,
                'uber_order_id' => $uberOrderId,
                'status' => 'SUBMITTED_TO_UBER'
            ];
        }

        return [
            'success' => false,
            'message' => $res['error'] ?? 'Uber order creation returned error',
        ];
    }

    public function getOrderStatus(string $externalOrderId): array
    {
        if (!$this->isSupportedAndApproved()) {
            return ['status' => 'LOCAL_ONLY'];
        }

        $res = $this->client->get("/v1/eats/orders/{$externalOrderId}");
        return $res['success'] ? $res['data'] : ['status' => 'UNKNOWN'];
    }

    public function cancelOrder(string $externalOrderId, string $reason): bool
    {
        if (!$this->isSupportedAndApproved()) {
            return false;
        }

        $res = $this->client->post("/v1/eats/orders/{$externalOrderId}/cancel", ['reason' => $reason]);
        return $res['success'];
    }
}
