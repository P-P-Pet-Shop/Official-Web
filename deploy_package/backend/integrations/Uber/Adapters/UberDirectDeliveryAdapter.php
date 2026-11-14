<?php
declare(strict_types=1);

namespace Uber\Adapters;

use App\Core\Config;
use App\Core\Logger;
use Uber\UberClient;

class UberDirectDeliveryAdapter
{
    private UberClient $client;

    public function __construct(?UberClient $client = null)
    {
        $this->client = $client ?? new UberClient();
    }

    public function isAvailableInRegion(): bool
    {
        // Currently Uber Direct availability in Sri Lanka requires specific regional partnership approval
        return (bool)Config::get('uber_direct_enabled', false);
    }

    public function getDeliveryQuote(array $pickupAddress, array $dropoffAddress): array
    {
        if (!$this->isAvailableInRegion()) {
            return [
                'success' => false,
                'message' => 'Uber Direct is not active in this operational region. Defaulting to internal delivery.',
            ];
        }

        $customerId = Config::get('uber_client_id');
        $payload = [
            'pickup_address' => json_encode($pickupAddress),
            'dropoff_address' => json_encode($dropoffAddress),
        ];

        return $this->client->post("/v1/customers/{$customerId}/delivery_quotes", $payload);
    }

    public function createDelivery(string $quoteId, array $order): array
    {
        if (!$this->isAvailableInRegion()) {
            return [
                'success' => false,
                'message' => 'Uber Direct is not active.',
            ];
        }

        $customerId = Config::get('uber_client_id');
        $payload = [
            'quote_id' => $quoteId,
            'manifest_reference' => $order['order_number'],
            'manifest_total_value' => (int)round(((float)$order['total_amount']) * 100),
            'pickup_name' => 'P and P Pet Shop',
            'pickup_phone_number' => '+94112712345',
            'dropoff_name' => $order['customer_name'],
            'dropoff_phone_number' => $order['customer_phone'],
            'dropoff_address' => $order['delivery_address'] . ', ' . $order['delivery_city'],
        ];

        return $this->client->post("/v1/customers/{$customerId}/deliveries", $payload);
    }

    public function getDeliveryStatus(string $deliveryId): array
    {
        $customerId = Config::get('uber_client_id');
        return $this->client->get("/v1/customers/{$customerId}/deliveries/{$deliveryId}");
    }
}
