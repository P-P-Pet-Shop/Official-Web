<?php
declare(strict_types=1);

namespace Uber;

use App\Core\Config;
use App\Core\Logger;
use App\Repositories\UberRepository;

class StoreService
{
    private UberClient $client;
    private UberRepository $repo;

    public function __construct(?UberClient $client = null, ?UberRepository $repo = null)
    {
        $this->client = $client ?? new UberClient();
        $this->repo = $repo ?? new UberRepository();
    }

    public function getStoreDetails(?string $storeId = null): array
    {
        $targetStoreId = $storeId ?: Config::get('uber_store_id', '');
        if (empty($targetStoreId)) {
            return [
                'success' => false,
                'error' => 'No Uber Store ID configured. Set UBER_STORE_ID in .env or settings.'
            ];
        }

        $res = $this->client->get("/v1/eats/stores/{$targetStoreId}");
        if (!$res['success']) {
            return $res;
        }

        $data = $res['data'];
        $name = $data['name'] ?? 'P and P Pet Shop';
        $status = $data['status'] ?? 'ACTIVE';
        $location = $data['location'] ?? [];
        $address = implode(', ', array_filter([
            $location['address'] ?? null,
            $location['city'] ?? null,
            $location['country'] ?? null,
        ]));

        $this->repo->saveStore($targetStoreId, $name, $status, $address, $data);

        return [
            'success' => true,
            'store_id' => $targetStoreId,
            'name' => $name,
            'status' => $status,
            'address' => $address,
            'raw' => $data,
        ];
    }
}
