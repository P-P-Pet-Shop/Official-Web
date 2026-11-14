<?php
declare(strict_types=1);

namespace Uber\Adapters;

use App\Core\Config;
use App\Core\Logger;
use Uber\UberClient;

class UberMarketplaceOrderAdapter
{
    private UberClient $client;

    public function __construct(?UberClient $client = null)
    {
        $this->client = $client ?? new UberClient();
    }

    public function getActiveOrders(string $storeId): array
    {
        $res = $this->client->get("/v1/eats/stores/{$storeId}/created-orders");
        return $res['success'] ? ($res['data']['orders'] ?? []) : [];
    }

    public function acceptOrder(string $uberOrderId, int $prepTimeMinutes = 20): bool
    {
        $res = $this->client->post("/v1/eats/orders/{$uberOrderId}/accept_pos_order", [
            'reason' => 'ACCEPTED',
            'prep_time' => $prepTimeMinutes
        ]);
        return $res['success'];
    }

    public function denyOrder(string $uberOrderId, string $explanation = 'OUT_OF_STOCK'): bool
    {
        $res = $this->client->post("/v1/eats/orders/{$uberOrderId}/deny_pos_order", [
            'reason' => [
                'explanation' => $explanation,
                'out_of_stock_items' => []
            ]
        ]);
        return $res['success'];
    }
}

