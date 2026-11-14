<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\CurrencyHelper;
use App\Repositories\OrderRepository;

class OrderController
{
    private OrderRepository $repo;

    public function __construct()
    {
        $this->repo = new OrderRepository();
    }

    public function track(Request $request, array $params): Response
    {
        $orderNumber = $params['orderNumber'] ?? '';
        $token = $request->getQuery('token');
        $phone = $request->getQuery('phone');

        $order = $this->repo->findByOrderNumber($orderNumber);

        if (!$order) {
            return Response::error('Order not found', 404, null, 'ORDER_NOT_FOUND');
        }

        // Security check: Either valid tracking token OR matching phone number must be provided
        $isTokenValid = $token && hash_equals($order['tracking_token'], (string)$token);
        $isPhoneValid = $phone && str_ends_with($order['customer_phone'], substr(preg_replace('/[^0-9]/', '', (string)$phone), -8));

        if (!$isTokenValid && !$isPhoneValid) {
            return Response::error('Verification required to track this order. Please supply the tracking link token or verification phone number.', 403, null, 'TRACKING_AUTH_REQUIRED');
        }

        // Format and sanitize output for public tracking
        $trackingData = [
            'order_number' => $order['order_number'],
            'created_at' => $order['created_at'],
            'customer_name' => $order['customer_name'],
            'delivery_city' => $order['delivery_city'],
            'delivery_district' => $order['delivery_district'],
            'website_order_status' => $order['website_order_status'],
            'delivery_status' => $order['delivery_status'],
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'subtotal' => $order['subtotal'],
            'subtotal_formatted' => CurrencyHelper::format($order['subtotal']),
            'delivery_fee' => $order['delivery_fee'],
            'delivery_fee_formatted' => CurrencyHelper::format($order['delivery_fee']),
            'total_amount' => $order['total_amount'],
            'total_amount_formatted' => CurrencyHelper::format($order['total_amount']),
            'currency' => $order['currency'],
            'items' => array_map(function ($item) {
                return [
                    'product_name' => $item['product_name'],
                    'variant_name' => $item['variant_name'],
                    'sku' => $item['sku'],
                    'quantity' => (int)$item['quantity'],
                    'unit_price_formatted' => CurrencyHelper::format($item['unit_price']),
                    'subtotal_formatted' => CurrencyHelper::format($item['subtotal']),
                ];
            }, $order['items']),
            'timeline' => array_map(function ($event) {
                return [
                    'status' => $event['new_status'],
                    'event_type' => $event['event_type'],
                    'description' => $event['description'],
                    'created_at' => $event['created_at'],
                ];
            }, $order['events']),
        ];

        return Response::success($trackingData, 'Order tracking details retrieved');
    }
}
