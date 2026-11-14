<?php
declare(strict_types=1);

namespace Uber;

use App\Core\Config;
use App\Core\Logger;

class MenuService
{
    private UberClient $client;

    public function __construct(?UberClient $client = null)
    {
        $this->client = $client ?? new UberClient();
    }

    public function getStoreMenu(?string $storeId = null): array
    {
        $targetStoreId = $storeId ?: Config::get('uber_store_id', '');
        if (empty($targetStoreId)) {
            return [
                'success' => false,
                'error' => 'No Uber Store ID configured.'
            ];
        }

        // Official Uber Eats v2 Menus API endpoint
        $res = $this->client->get("/v2/eats/stores/{$targetStoreId}/menus");
        if (!$res['success']) {
            // Fallback attempt to v1 if v2 returns 404
            if (($res['status_code'] ?? 0) === 404) {
                Logger::uber('INFO', "Menu v2 not found, falling back to v1 menu endpoint...");
                return $this->client->get("/v1/eats/stores/{$targetStoreId}/menus");
            }
            return $res;
        }

        return $res;
    }
}
