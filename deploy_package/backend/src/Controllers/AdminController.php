<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\CurrencyHelper;
use App\Helpers\StringHelper;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UberRepository;
use Uber\OAuthManager;
use Uber\ProductSyncService;
use Uber\InventorySyncService;
use Uber\StoreService;
use PDO;

class AdminController
{
    private ProductRepository $productRepo;
    private OrderRepository $orderRepo;
    private UberRepository $uberRepo;
    private PDO $db;

    public function __construct()
    {
        $this->productRepo = new ProductRepository();
        $this->orderRepo = new OrderRepository();
        $this->uberRepo = new UberRepository();
        $this->db = Database::getInstance()->getConnection();
    }

    public function dashboard(Request $request): Response
    {
        // 1. Order Stats
        $ordersCount = (int)$this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $pendingCount = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE website_order_status = 'PENDING'")->fetchColumn();
        $totalRevenue = (float)$this->db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'PAID'")->fetchColumn();

        // 2. Product Stats
        $productsCount = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
        $lowStockCount = (int)$this->db->query("SELECT COUNT(*) FROM inventory WHERE (quantity_on_hand - quantity_reserved) <= low_stock_threshold")->fetchColumn();

        // 3. Customer Stats
        $customersCount = (int)$this->db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();

        // 4. Uber Stats
        $activeStore = $this->uberRepo->getActiveStore();
        $mappedCount = (int)$this->db->query("SELECT COUNT(*) FROM uber_product_mappings WHERE sync_status = 'SYNCED'")->fetchColumn();
        $recentLogs = $this->uberRepo->getRecentSyncLogs(5);

        return Response::success([
            'metrics' => [
                'total_orders' => $ordersCount,
                'pending_orders' => $pendingCount,
                'total_revenue' => $totalRevenue,
                'total_revenue_formatted' => CurrencyHelper::format($totalRevenue),
                'total_products' => $productsCount,
                'low_stock_products' => $lowStockCount,
                'total_customers' => $customersCount,
                'uber_synced_products' => $mappedCount,
            ],
            'uber_status' => [
                'is_connected' => !empty($activeStore) || !empty(Config::get('uber_access_token')),
                'store_id' => $activeStore['uber_store_id'] ?? Config::get('uber_store_id', 'Not set'),
                'store_name' => $activeStore['store_name'] ?? 'P and P Pet Shop',
                'store_status' => $activeStore['status'] ?? 'UNKNOWN',
                'last_synced_at' => $activeStore['last_synced_at'] ?? null,
            ],
            'recent_sync_logs' => $recentLogs,
        ]);
    }

    public function getOrders(Request $request): Response
    {
        $status = $request->getQuery('status');
        $search = $request->getQuery('search');
        $page = max(1, (int)$request->getQuery('page', 1));

        $filters = array_filter(['status' => $status, 'search' => $search]);
        $result = $this->orderRepo->getPaginated($filters, $page, 20);

        return Response::success($result['orders'], 'Orders retrieved', 200, $result['pagination']);
    }

    public function getOrderDetails(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $order = $this->orderRepo->findById($id);

        if (!$order) {
            return Response::error('Order not found', 404);
        }

        return Response::success($order);
    }

    public function updateOrderStatus(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $body = $request->getBody();
        $newStatus = $body['status'] ?? '';
        $note = $body['note'] ?? 'Status updated by administrator.';

        $validStatuses = ['PENDING', 'VALIDATING', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP', 'PICKED_UP', 'DELIVERED', 'REJECTED', 'CANCELLED'];
        if (!in_array($newStatus, $validStatuses, true)) {
            return Response::error('Invalid order status transition', 400);
        }

        $success = $this->orderRepo->updateStatus($id, $newStatus, 'ADMIN_UPDATE', $note, 'ADMIN');
        if (!$success) {
            return Response::error('Order not found', 404);
        }

        return Response::success(['order_id' => $id, 'status' => $newStatus], 'Order status updated successfully');
    }

    public function getProducts(Request $request): Response
    {
        $category = $request->getQuery('category');
        $search = $request->getQuery('search');
        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = 25;

        $filters = array_filter(['category' => $category, 'search' => $search]);
        $result = $this->productRepo->findPaginated($filters, $page, $perPage);

        return Response::success($result['items'], 'Products retrieved', 200, $result['pagination']);
    }

    public function createProduct(Request $request): Response
    {
        $body = $request->getBody();

        if (empty($body['name']) || empty($body['category_id']) || !isset($body['base_price'])) {
            return Response::error('Missing required fields: name, category_id, base_price', 422);
        }

        $slug = !empty($body['slug']) ? StringHelper::slugify($body['slug']) : StringHelper::slugify($body['name']);
        $sku = !empty($body['sku']) ? trim($body['sku']) : 'PNP-' . strtoupper(bin2hex(random_bytes(3)));

        $data = [
            'category_id' => (int)$body['category_id'],
            'name' => trim($body['name']),
            'slug' => $slug,
            'sku' => $sku,
            'description' => $body['description'] ?? null,
            'short_description' => $body['short_description'] ?? null,
            'base_price' => (float)$body['base_price'],
            'sale_price' => !empty($body['sale_price']) ? (float)$body['sale_price'] : null,
            'currency' => 'LKR',
            'is_active' => isset($body['is_active']) ? (int)$body['is_active'] : 1,
            'is_featured' => !empty($body['is_featured']) ? 1 : 0,
            'is_promotional' => !empty($body['is_promotional']) ? 1 : 0,
            'track_inventory' => isset($body['track_inventory']) ? (int)$body['track_inventory'] : 1,
        ];

        $productId = $this->productRepo->create($data);

        // Add primary image if provided
        if (!empty($body['image_url'])) {
            $imgStmt = $this->db->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (:p_id, :url, 1)");
            $imgStmt->execute(['p_id' => $productId, 'url' => trim($body['image_url'])]);
        }

        // Initialize inventory record
        $invStmt = $this->db->prepare("INSERT INTO inventory (product_id, quantity_on_hand) VALUES (:p_id, :qty)");
        $invStmt->execute(['p_id' => $productId, 'qty' => (int)($body['stock_quantity'] ?? 10)]);

        return Response::success(['id' => $productId, 'slug' => $slug], 'Product created successfully', 201);
    }

    public function updateProduct(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $body = $request->getBody();

        $updated = $this->productRepo->update($id, $body);
        if (!$updated) {
            return Response::error('Product not found or no changes supplied', 400);
        }

        return Response::success(['id' => $id], 'Product updated successfully');
    }

    public function deleteProduct(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $this->productRepo->delete($id);
        return Response::success(null, 'Product deactivated successfully');
    }

    public function getCategories(Request $request): Response
    {
        $stmt = $this->db->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
        return Response::success($stmt->fetchAll());
    }

    public function createCategory(Request $request): Response
    {
        $body = $request->getBody();
        if (empty($body['name'])) {
            return Response::error('Category name is required', 422);
        }

        $slug = !empty($body['slug']) ? StringHelper::slugify($body['slug']) : StringHelper::slugify($body['name']);
        $stmt = $this->db->prepare("
            INSERT INTO categories (name, slug, description, image_url, sort_order)
            VALUES (:name, :slug, :desc, :img, :sort)
        ");
        $stmt->execute([
            'name' => trim($body['name']),
            'slug' => $slug,
            'desc' => $body['description'] ?? null,
            'img' => $body['image_url'] ?? null,
            'sort' => (int)($body['sort_order'] ?? 0),
        ]);

        return Response::success(['id' => (int)$this->db->lastInsertId()], 'Category created', 201);
    }

    public function getCustomers(Request $request): Response
    {
        $stmt = $this->db->query("
            SELECT c.*, COUNT(o.id) as total_orders, COALESCE(SUM(o.total_amount), 0) as total_spent
            FROM customers c
            LEFT JOIN orders o ON c.id = o.customer_id
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT 50
        ");
        return Response::success($stmt->fetchAll());
    }

    public function getInventory(Request $request): Response
    {
        $stmt = $this->db->query("
            SELECT i.*, p.name as product_name, p.sku as product_sku, v.variant_name
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            LEFT JOIN product_variants v ON i.variant_id = v.id
            ORDER BY (i.quantity_on_hand - i.quantity_reserved) ASC
            LIMIT 100
        ");
        return Response::success($stmt->fetchAll());
    }

    public function updateInventory(Request $request, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $body = $request->getBody();

        if (!isset($body['quantity_on_hand'])) {
            return Response::error('quantity_on_hand is required', 422);
        }

        $stmt = $this->db->prepare("
            UPDATE inventory 
            SET quantity_on_hand = :qty, 
                reorder_level = :reorder,
                low_stock_threshold = :low,
                last_restocked_at = UTC_TIMESTAMP()
            WHERE id = :id
        ");
        $stmt->execute([
            'qty' => (int)$body['quantity_on_hand'],
            'reorder' => (int)($body['reorder_level'] ?? 5),
            'low' => (int)($body['low_stock_threshold'] ?? 2),
            'id' => $id,
        ]);

        return Response::success(['id' => $id], 'Inventory updated');
    }

    public function uberStatus(Request $request): Response
    {
        $oauth = new OAuthManager();
        $storeService = new StoreService();
        $storeDetails = $storeService->getStoreDetails();

        return Response::success([
            'is_connected' => $oauth->isConnected(),
            'store' => $storeDetails['success'] ? $storeDetails : null,
            'recent_logs' => $this->uberRepo->getRecentSyncLogs(10),
            'environment' => Config::get('uber_environment', 'sandbox'),
        ]);
    }

    public function uberSync(Request $request): Response
    {
        $body = $request->getBody();
        $type = $body['type'] ?? 'ALL'; // 'CATALOG', 'PRICE', 'INVENTORY', or 'ALL'

        $results = [];

        if (in_array($type, ['CATALOG', 'ALL'], true)) {
            $catService = new ProductSyncService();
            $results['catalog'] = $catService->syncAllProducts();
        }

        if (in_array($type, ['PRICE', 'ALL'], true)) {
            $invService = new InventorySyncService();
            $results['price'] = $invService->syncPrices();
        }

        if (in_array($type, ['INVENTORY', 'ALL'], true)) {
            $invService = new InventorySyncService();
            $results['inventory'] = $invService->syncInventory();
        }

        return Response::success($results, 'Uber synchronization triggered');
    }

    public function uberSyncLogs(Request $request): Response
    {
        $logs = $this->uberRepo->getRecentSyncLogs(50);
        return Response::success($logs);
    }

    public function uberMappings(Request $request): Response
    {
        $mappings = $this->uberRepo->getAllMappings(100);
        return Response::success($mappings);
    }

    public function getSettings(Request $request): Response
    {
        $stmt = $this->db->query("SELECT setting_group, setting_key, setting_value, description FROM integration_settings");
        return Response::success($stmt->fetchAll());
    }

    public function saveSettings(Request $request): Response
    {
        $body = $request->getBody();
        $stmt = $this->db->prepare("
            INSERT INTO integration_settings (setting_group, setting_key, setting_value)
            VALUES (:grp, :k, :v)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = UTC_TIMESTAMP()
        ");

        foreach ($body as $key => $val) {
            $stmt->execute([
                'grp' => 'GENERAL',
                'k' => $key,
                'v' => is_scalar($val) ? (string)$val : json_encode($val),
            ]);
        }

        return Response::success(null, 'Settings saved successfully');
    }
}
