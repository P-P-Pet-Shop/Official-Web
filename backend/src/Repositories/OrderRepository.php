<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class OrderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $order['items'] = $this->getOrderItems($id);
        $order['events'] = $this->getOrderEvents($id);
        $order['payments'] = $this->getOrderPayments($id);
        $order['deliveries'] = $this->getOrderDeliveries($id);

        return $order;
    }

    public function findByOrderNumber(string $orderNumber): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_number = :order_number LIMIT 1");
        $stmt->execute(['order_number' => $orderNumber]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $order['items'] = $this->getOrderItems((int)$order['id']);
        $order['events'] = $this->getOrderEvents((int)$order['id']);
        $order['payments'] = $this->getOrderPayments((int)$order['id']);
        $order['deliveries'] = $this->getOrderDeliveries((int)$order['id']);

        return $order;
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE idempotency_key = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $order = $stmt->fetch();

        if ($order) {
            $order['items'] = $this->getOrderItems((int)$order['id']);
        }
        return $order ?: null;
    }

    public function createOrder(array $orderData, array $items): array
    {
        $this->db->beginTransaction();

        try {
            // Check idempotency first
            if (!empty($orderData['idempotency_key'])) {
                $existing = $this->findByIdempotencyKey($orderData['idempotency_key']);
                if ($existing) {
                    $this->db->commit();
                    return $existing;
                }
            }

            // Insert Order
            $stmt = $this->db->prepare("
                INSERT INTO orders (
                    order_number, customer_id, customer_name, customer_email, customer_phone,
                    delivery_address, delivery_city, delivery_district, delivery_instructions,
                    payment_method, payment_status, website_order_status, uber_order_status,
                    delivery_status, subtotal, delivery_fee, discount_amount, total_amount,
                    currency, idempotency_key, tracking_token, notes
                ) VALUES (
                    :order_number, :customer_id, :customer_name, :customer_email, :customer_phone,
                    :delivery_address, :delivery_city, :delivery_district, :delivery_instructions,
                    :payment_method, :payment_status, :website_order_status, :uber_order_status,
                    :delivery_status, :subtotal, :delivery_fee, :discount_amount, :total_amount,
                    :currency, :idempotency_key, :tracking_token, :notes
                )
            ");

            $stmt->execute([
                'order_number' => $orderData['order_number'],
                'customer_id' => $orderData['customer_id'] ?? null,
                'customer_name' => $orderData['customer_name'],
                'customer_email' => $orderData['customer_email'] ?? null,
                'customer_phone' => $orderData['customer_phone'],
                'delivery_address' => $orderData['delivery_address'],
                'delivery_city' => $orderData['delivery_city'],
                'delivery_district' => $orderData['delivery_district'] ?? 'Colombo',
                'delivery_instructions' => $orderData['delivery_instructions'] ?? null,
                'payment_method' => $orderData['payment_method'] ?? 'CASH_ON_DELIVERY',
                'payment_status' => $orderData['payment_status'] ?? 'PENDING',
                'website_order_status' => $orderData['website_order_status'] ?? 'PENDING',
                'uber_order_status' => $orderData['uber_order_status'] ?? 'NOT_INTEGRATED',
                'delivery_status' => $orderData['delivery_status'] ?? 'PENDING',
                'subtotal' => $orderData['subtotal'],
                'delivery_fee' => $orderData['delivery_fee'] ?? 0.00,
                'discount_amount' => $orderData['discount_amount'] ?? 0.00,
                'total_amount' => $orderData['total_amount'],
                'currency' => $orderData['currency'] ?? 'LKR',
                'idempotency_key' => $orderData['idempotency_key'],
                'tracking_token' => $orderData['tracking_token'],
                'notes' => $orderData['notes'] ?? null,
            ]);

            $orderId = (int)$this->db->lastInsertId();

            // Insert Order Items and Reserve Stock
            $itemStmt = $this->db->prepare("
                INSERT INTO order_items (
                    order_id, product_id, variant_id, product_name, variant_name,
                    sku, unit_price, quantity, subtotal
                ) VALUES (
                    :order_id, :product_id, :variant_id, :product_name, :variant_name,
                    :sku, :unit_price, :quantity, :subtotal
                )
            ");

            $stockStmt = $this->db->prepare("
                UPDATE inventory 
                SET quantity_reserved = quantity_reserved + :qty 
                WHERE product_id = :product_id 
                  AND (variant_id = :variant_id OR (:variant_id IS NULL AND variant_id IS NULL))
            ");

            foreach ($items as $item) {
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'variant_name' => $item['variant_name'] ?? null,
                    'sku' => $item['sku'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);

                // Reserve inventory
                $stockStmt->execute([
                    'qty' => $item['quantity'],
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                ]);
            }

            // Create initial Order Event
            $this->addEvent(
                $orderId,
                null,
                $orderData['website_order_status'] ?? 'PENDING',
                'ORDER_PLACED',
                'Order placed successfully by customer via website checkout.',
                'CUSTOMER'
            );

            // Record initial payment record
            $payStmt = $this->db->prepare("
                INSERT INTO payments (order_id, payment_method, amount, currency, payment_status)
                VALUES (:order_id, :method, :amount, :currency, :status)
            ");
            $payStmt->execute([
                'order_id' => $orderId,
                'method' => $orderData['payment_method'] ?? 'CASH_ON_DELIVERY',
                'amount' => $orderData['total_amount'],
                'currency' => $orderData['currency'] ?? 'LKR',
                'status' => $orderData['payment_status'] ?? 'PENDING',
            ]);

            // Record initial delivery record
            $delStmt = $this->db->prepare("
                INSERT INTO deliveries (order_id, delivery_provider, delivery_status, delivery_fee)
                VALUES (:order_id, 'INTERNAL', 'PENDING', :fee)
            ");
            $delStmt->execute([
                'order_id' => $orderId,
                'fee' => $orderData['delivery_fee'] ?? 0.00,
            ]);

            $this->db->commit();

            return $this->findById($orderId) ?? [];

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException("Failed to create order: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateStatus(int $orderId, string $newStatus, string $eventType, string $description, string $triggeredBy = 'SYSTEM'): bool
    {
        $stmt = $this->db->prepare("SELECT website_order_status FROM orders WHERE id = :id");
        $stmt->execute(['id' => $orderId]);
        $currentStatus = $stmt->fetchColumn();

        if (!$currentStatus) {
            return false;
        }

        $updateStmt = $this->db->prepare("UPDATE orders SET website_order_status = :status WHERE id = :id");
        $updateStmt->execute(['status' => $newStatus, 'id' => $orderId]);

        $this->addEvent($orderId, (string)$currentStatus, $newStatus, $eventType, $description, $triggeredBy);

        return true;
    }

    public function addEvent(int $orderId, ?string $prevStatus, string $newStatus, string $type, string $desc, string $by = 'SYSTEM'): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO order_events (order_id, previous_status, new_status, event_type, description, triggered_by)
            VALUES (:order_id, :prev, :new, :type, :desc, :by)
        ");
        $stmt->execute([
            'order_id' => $orderId,
            'prev' => $prevStatus,
            'new' => $newStatus,
            'type' => $type,
            'desc' => $desc,
            'by' => $by,
        ]);
    }

    public function getOrderItems(int $orderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC");
        $stmt->execute(['id' => $orderId]);
        return $stmt->fetchAll();
    }

    public function getOrderEvents(int $orderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM order_events WHERE order_id = :id ORDER BY created_at ASC");
        $stmt->execute(['id' => $orderId]);
        return $stmt->fetchAll();
    }

    public function getOrderPayments(int $orderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE order_id = :id ORDER BY id ASC");
        $stmt->execute(['id' => $orderId]);
        return $stmt->fetchAll();
    }

    public function getOrderDeliveries(int $orderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM deliveries WHERE order_id = :id ORDER BY id ASC");
        $stmt->execute(['id' => $orderId]);
        return $stmt->fetchAll();
    }

    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'website_order_status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['customer_id'])) {
            $where[] = 'customer_id = :customer_id';
            $params['customer_id'] = (int)$filters['customer_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(order_number LIKE :s OR customer_name LIKE :s OR customer_phone LIKE :s)';
            $params['s'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT * FROM orders 
            WHERE {$whereClause} 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $orders = $stmt->fetchAll();

        return [
            'orders' => $orders,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ]
        ];
    }
}

