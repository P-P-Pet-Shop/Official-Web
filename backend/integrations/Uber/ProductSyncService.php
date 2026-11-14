<?php
declare(strict_types=1);

namespace Uber;

use App\Core\Database;
use App\Core\Logger;
use App\Helpers\StringHelper;
use App\Repositories\UberRepository;
use PDO;

class ProductSyncService
{
    private MenuService $menuService;
    private UberRepository $repo;
    private PDO $db;

    public function __construct(?MenuService $menuService = null, ?UberRepository $repo = null)
    {
        $this->menuService = $menuService ?? new MenuService();
        $this->repo = $repo ?? new UberRepository();
        $this->db = Database::getInstance()->getConnection();
    }

    public function syncAllProducts(): array
    {
        $logId = $this->repo->startSyncLog('CATALOG');
        Logger::uber('INFO', "Started Uber Eats catalog synchronization [Log ID: {$logId}]");

        $menuResult = $this->menuService->getStoreMenu();
        if (!$menuResult['success']) {
            $err = $menuResult['error'] ?? 'Failed to retrieve menu from Uber Eats API';
            $this->repo->finishSyncLog($logId, 'FAILED', 0, 0, 0, $err);
            return [
                'success' => false,
                'message' => $err,
            ];
        }

        $itemsProcessed = 0;
        $itemsUpdated = 0;
        $itemsFailed = 0;

        $menuData = $menuResult['data'];
        $items = $menuData['items'] ?? [];

        // If items are nested inside categories or sections, extract them
        if (empty($items) && isset($menuData['categories'])) {
            foreach ($menuData['categories'] as $cat) {
                if (isset($cat['entities'])) {
                    foreach ($cat['entities'] as $entity) {
                        $items[] = $entity;
                    }
                }
            }
        }

        foreach ($items as $uberItem) {
            $itemsProcessed++;
            try {
                $uberId = $uberItem['id'] ?? $uberItem['item_id'] ?? null;
                $title = $uberItem['title'] ?? $uberItem['name'] ?? null;
                $priceRaw = $uberItem['price'] ?? 0;
                // Uber represents currency in cents/subunits or decimal
                $price = is_numeric($priceRaw) ? ($priceRaw > 1000 ? $priceRaw / 100 : (float)$priceRaw) : 0.0;
                $externalId = $uberItem['external_data'] ?? $uberItem['merchant_id'] ?? null;

                if (!$uberId || !$title) {
                    $itemsFailed++;
                    continue;
                }

                // Check if mapping exists
                $stmt = $this->db->prepare("SELECT * FROM uber_product_mappings WHERE uber_item_id = :id LIMIT 1");
                $stmt->execute(['id' => $uberId]);
                $mapping = $stmt->fetch();

                if ($mapping) {
                    // Update price & availability
                    $this->repo->saveMapping((int)$mapping['product_id'], $mapping['variant_id'] ? (int)$mapping['variant_id'] : null, $uberId, $price, true, 'SYNCED');
                    $itemsUpdated++;
                } else {
                    // Try to match by SKU / merchant ID or name
                    $pStmt = $this->db->prepare("SELECT id, base_price FROM products WHERE sku = :sku OR name = :name LIMIT 1");
                    $pStmt->execute([
                        'sku' => $externalId ?: $uberId,
                        'name' => $title
                    ]);
                    $product = $pStmt->fetch();

                    if ($product) {
                        $this->repo->saveMapping((int)$product['id'], null, $uberId, $price, true, 'SYNCED');
                        $itemsUpdated++;
                    } else {
                        // Product exists on Uber but not on website yet; log pending mapping
                        Logger::uber('INFO', "Unmapped Uber item discovered: '{$title}' (ID: {$uberId})");
                    }
                }
            } catch (\Throwable $e) {
                $itemsFailed++;
                Logger::uber('ERROR', "Failed syncing item: " . $e->getMessage());
            }
        }

        $this->repo->finishSyncLog($logId, 'SUCCESS', $itemsProcessed, $itemsUpdated, $itemsFailed);

        return [
            'success' => true,
            'message' => "Catalog synchronization completed. Processed: {$itemsProcessed}, Updated: {$itemsUpdated}, Errors: {$itemsFailed}",
            'processed' => $itemsProcessed,
            'updated' => $itemsUpdated,
            'failed' => $itemsFailed,
        ];
    }
}

