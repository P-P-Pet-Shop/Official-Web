<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Helpers\CurrencyHelper;
use App\Helpers\StringHelper;
use App\Repositories\OrderRepository;
use RuntimeException;
use Uber\Adapters\UberOrderAdapterInterface;
use Uber\Adapters\UberExternalOrderAdapter;

class CheckoutService
{
    private CartService $cartService;
    private OrderRepository $orderRepo;

    public function __construct()
    {
        $this->cartService = new CartService();
        $this->orderRepo = new OrderRepository();
    }

    public function processCheckout(array $data, ?int $customerId = null): array
    {
        // 1. Validate customer input
        $customerName = trim($data['customer_name'] ?? '');
        $customerPhone = trim($data['customer_phone'] ?? '');
        $customerEmail = !empty($data['customer_email']) ? trim($data['customer_email']) : null;
        $deliveryAddress = trim($data['delivery_address'] ?? '');
        $deliveryCity = trim($data['delivery_city'] ?? '');
        $deliveryDistrict = trim($data['delivery_district'] ?? 'Colombo');
        $deliveryInstructions = !empty($data['delivery_instructions']) ? trim($data['delivery_instructions']) : null;
        $paymentMethod = $data['payment_method'] ?? 'CASH_ON_DELIVERY';
        $idempotencyKey = !empty($data['idempotency_key']) ? trim($data['idempotency_key']) : StringHelper::generateIdempotencyKey();

        $errors = [];
        if (strlen($customerName) < 2) {
            $errors['customer_name'] = 'Full name is required (min 2 characters).';
        }

        // Validate Sri Lanka phone numbers (07XXXXXXXX or +947XXXXXXXX or 011XXXXXXX)
        $cleanPhone = preg_replace('/[^0-9+]/', '', $customerPhone);
        if (!preg_match('/^(\+94|0)?[1-9][0-9]{7,8}$/', $cleanPhone)) {
            $errors['customer_phone'] = 'Please provide a valid Sri Lankan mobile or contact number.';
        }

        if ($customerEmail && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'] = 'Invalid email address format.';
        }

        if (strlen($deliveryAddress) < 5) {
            $errors['delivery_address'] = 'Detailed delivery address is required.';
        }

        if (strlen($deliveryCity) < 2) {
            $errors['delivery_city'] = 'City or area is required.';
        }

        if (!in_array($paymentMethod, ['CASH_ON_DELIVERY', 'BANK_TRANSFER', 'ONLINE_CARD'], true)) {
            $errors['payment_method'] = 'Unsupported payment method.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed. Please correct the highlighted fields.',
                'errors' => $errors,
            ];
        }

        // 2. Recalculate cart server-side
        $rawItems = $data['items'] ?? [];
        $cartResult = $this->cartService->validateAndCalculate($rawItems, $deliveryDistrict);

        if (!$cartResult['is_valid']) {
            return [
                'success' => false,
                'message' => 'Cart validation failed.',
                'errors' => $cartResult['errors'],
            ];
        }

        // 3. Prepare Order Data
        $orderNumber = StringHelper::generateOrderNumber();
        $trackingToken = StringHelper::generateTrackingToken();

        $orderData = [
            'order_number' => $orderNumber,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $cleanPhone,
            'delivery_address' => $deliveryAddress,
            'delivery_city' => $deliveryCity,
            'delivery_district' => $deliveryDistrict,
            'delivery_instructions' => $deliveryInstructions,
            'payment_method' => $paymentMethod,
            'payment_status' => 'PENDING',
            'website_order_status' => 'PENDING',
            'uber_order_status' => 'NOT_INTEGRATED',
            'delivery_status' => 'PENDING',
            'subtotal' => $cartResult['subtotal'],
            'delivery_fee' => $cartResult['delivery_fee'],
            'discount_amount' => 0.00,
            'total_amount' => $cartResult['total'],
            'currency' => CurrencyHelper::getCurrencyCode(),
            'idempotency_key' => $idempotencyKey,
            'tracking_token' => $trackingToken,
            'notes' => $data['notes'] ?? null,
        ];

        // 4. Save to Database within Transaction
        try {
            $savedOrder = $this->orderRepo->createOrder($orderData, $cartResult['items']);
        } catch (RuntimeException $e) {
            Logger::error("Order creation failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to place order. ' . $e->getMessage(),
            ];
        }

        // 5. Attempt External Integration via Adapter (Phase 8.6)
        $this->dispatchOrderIntegration($savedOrder);

        return [
            'success' => true,
            'message' => 'Your order has been placed successfully!',
            'order' => [
                'id' => $savedOrder['id'],
                'order_number' => $savedOrder['order_number'],
                'tracking_token' => $savedOrder['tracking_token'],
                'total_amount' => $savedOrder['total_amount'],
                'total_amount_formatted' => CurrencyHelper::format($savedOrder['total_amount']),
                'payment_method' => $savedOrder['payment_method'],
                'payment_status' => $savedOrder['payment_status'],
                'website_order_status' => $savedOrder['website_order_status'],
                'created_at' => $savedOrder['created_at'],
                'items_count' => count($cartResult['items']),
                'tracking_url' => "/tracking?order=" . urlencode($savedOrder['order_number']) . "&token=" . urlencode($savedOrder['tracking_token']),
            ]
        ];
    }

    private function dispatchOrderIntegration(array $order): void
    {
        try {
            // Instantiate UberExternalOrderAdapter
            $adapter = new UberExternalOrderAdapter();
            if ($adapter->isSupportedAndApproved()) {
                $adapter->submitOrder($order);
            } else {
                Logger::info("Uber external order adapter is not currently active/approved for this merchant. Order #{$order['order_number']} is queued for local fulfillment.");
            }
        } catch (\Throwable $e) {
            Logger::error("Order integration dispatch exception: " . $e->getMessage());
        }
    }
}
