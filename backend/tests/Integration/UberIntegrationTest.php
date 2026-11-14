<?php
declare(strict_types=1);

use App\Core\Config;
use App\Services\CartService;
use Uber\Adapters\UberExternalOrderAdapter;
use Uber\WebhookService;

class UberIntegrationTest
{
    private TestRunner $runner;

    public function __construct(TestRunner $runner)
    {
        $this->runner = $runner;
    }

    public function execute(): void
    {
        // 1. External Order Adapter Approval Guard
        Config::set('uber_external_order_injection_approved', 'false');
        $adapter = new UberExternalOrderAdapter();
        $this->runner->assert(!$adapter->isSupportedAndApproved(), 'UberExternalOrderAdapter is safely disabled when not approved');

        // 2. Webhook Signature Verification
        Config::set('uber_webhook_signing_key', 'test_secret_key_123');
        $webhookService = new WebhookService();
        $payload = json_encode(['event_id' => 'evt_101', 'event_type' => 'eats.order.status_update']);
        $validSignature = hash_hmac('sha256', $payload, 'test_secret_key_123');
        
        $this->runner->assert($webhookService->verifySignature($payload, $validSignature), 'Validates genuine Uber webhook HMAC signature');
        $this->runner->assert(!$webhookService->verifySignature($payload, 'invalid_signature'), 'Rejects forged webhook signature');

        // 3. Delivery Fee Policy
        $cartService = new CartService();
        $colomboFee = $cartService->calculateDeliveryFee(5000, 'Colombo');
        $this->runner->assert($colomboFee === 350.00, 'Applies standard delivery fee of Rs. 350 for Colombo');

        $freeDelivery = $cartService->calculateDeliveryFee(16000, 'Colombo');
        $this->runner->assert($freeDelivery === 0.0, 'Applies free delivery for Colombo orders over Rs. 15,000');

        $outstationFee = $cartService->calculateDeliveryFee(5000, 'Kandy');
        $this->runner->assert($outstationFee === 650.00, 'Applies outstation delivery fee of Rs. 650');
    }
}

