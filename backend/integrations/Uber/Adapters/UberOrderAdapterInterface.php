<?php
declare(strict_types=1);

namespace Uber\Adapters;

interface UberOrderAdapterInterface
{
    /**
     * Determine if this adapter has verified credentials and active approved permissions.
     */
    public function isSupportedAndApproved(): bool;

    /**
     * Submit an order to the external provider.
     */
    public function submitOrder(array $order): array;

    /**
     * Query order status from the external provider.
     */
    public function getOrderStatus(string $externalOrderId): array;

    /**
     * Cancel an external order where supported.
     */
    public function cancelOrder(string $externalOrderId, string $reason): bool;
}

