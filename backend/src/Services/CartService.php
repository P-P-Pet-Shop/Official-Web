<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\CurrencyHelper;
use PDO;

class CartService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function validateAndCalculate(array $rawItems, string $district = 'Colombo'): array
    {
        $validatedItems = [];
        $subtotal = 0.0;
        $totalItemsCount = 0;
        $hasErrors = false;
        $errors = [];

        if (empty($rawItems)) {
            return [
                'is_valid' => false,
                'items' => [],
                'subtotal' => 0.0,
                'subtotal_formatted' => CurrencyHelper::format(0),
                'delivery_fee' => 0.0,
                'delivery_fee_formatted' => CurrencyHelper::format(0),
                'total' => 0.0,
                'total_formatted' => CurrencyHelper::format(0),
                'total_items_count' => 0,
                'currency' => CurrencyHelper::getCurrencyCode(),
                'errors' => ['Cart is empty.'],
            ];
        }

        foreach ($rawItems as $index => $raw) {
            $productId = (int)($raw['product_id'] ?? 0);
            $variantId = !empty($raw['variant_id']) ? (int)$raw['variant_id'] : null;
            $quantity = max(1, (int)($raw['quantity'] ?? 1));

            // Fetch live product
            $pStmt = $this->db->prepare("
                SELECT p.*,
                       (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image
                FROM products p 
                WHERE p.id = :id AND p.is_active = 1
                LIMIT 1
            ");
            $pStmt->execute(['id' => $productId]);
            $product = $pStmt->fetch();

            if (!$product) {
                $hasErrors = true;
                $errors[] = "Item at position " . ($index + 1) . " is no longer available.";
                continue;
            }

            $unitPrice = (float)($product['sale_price'] ?? $product['base_price']);
            $variantName = null;
            $sku = $product['sku'];
            $maxStock = 999;

            // Check variant if applicable
            if ($variantId !== null) {
                $vStmt = $this->db->prepare("
                    SELECT v.*, COALESCE(i.quantity_on_hand - i.quantity_reserved, 0) as stock
                    FROM product_variants v
                    LEFT JOIN inventory i ON v.id = i.variant_id
                    WHERE v.id = :v_id AND v.product_id = :p_id AND v.is_active = 1
                    LIMIT 1
                ");
                $vStmt->execute(['v_id' => $variantId, 'p_id' => $productId]);
                $variant = $vStmt->fetch();

                if (!$variant) {
                    $hasErrors = true;
                    $errors[] = "Selected variant for '{$product['name']}' is invalid or discontinued.";
                    continue;
                }

                $variantName = $variant['variant_name'];
                $sku = $variant['sku'];
                $unitPrice += (float)$variant['additional_price'];
                $maxStock = (int)$variant['stock'];
            } else {
                // Product-level stock
                $sStmt = $this->db->prepare("
                    SELECT COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) as stock
                    FROM inventory WHERE product_id = :id
                ");
                $sStmt->execute(['id' => $productId]);
                $maxStock = (int)$sStmt->fetchColumn();
            }

            // Check inventory if tracked
            if ((bool)$product['track_inventory'] && $quantity > $maxStock) {
                $hasErrors = true;
                $errors[] = "Requested quantity ({$quantity}) for '{$product['name']}' exceeds available stock ({$maxStock}).";
                $quantity = max(1, $maxStock);
            }

            $itemSubtotal = $unitPrice * $quantity;
            $subtotal += $itemSubtotal;
            $totalItemsCount += $quantity;

            $validatedItems[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'product_name' => $product['name'],
                'variant_name' => $variantName,
                'sku' => $sku,
                'slug' => $product['slug'],
                'image_url' => $product['primary_image'] ?? '/assets/100002.png',
                'unit_price' => $unitPrice,
                'unit_price_formatted' => CurrencyHelper::format($unitPrice),
                'quantity' => $quantity,
                'available_stock' => $maxStock,
                'subtotal' => $itemSubtotal,
                'subtotal_formatted' => CurrencyHelper::format($itemSubtotal),
            ];
        }

        // Calculate delivery fee
        $deliveryFee = $this->calculateDeliveryFee($subtotal, $district);
        $total = $subtotal + $deliveryFee;

        return [
            'is_valid' => !$hasErrors && count($validatedItems) > 0,
            'items' => $validatedItems,
            'subtotal' => $subtotal,
            'subtotal_formatted' => CurrencyHelper::format($subtotal),
            'delivery_fee' => $deliveryFee,
            'delivery_fee_formatted' => CurrencyHelper::format($deliveryFee),
            'total' => $total,
            'total_formatted' => CurrencyHelper::format($total),
            'total_items_count' => $totalItemsCount,
            'currency' => CurrencyHelper::getCurrencyCode(),
            'errors' => $errors,
        ];
    }

    public function calculateDeliveryFee(float $subtotal, string $district = 'Colombo'): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        // Free delivery for orders above LKR 15,000 within Colombo & Mount Lavinia
        $normalizedDistrict = strtolower(trim($district));
        if ($normalizedDistrict === 'colombo') {
            if ($subtotal >= 15000.0) {
                return 0.0;
            }
            return 350.00; // Flat local rate
        }

        if (in_array($normalizedDistrict, ['gampaha', 'kalutara'], true)) {
            return 450.00;
        }

        // Outstation Sri Lanka delivery
        return 650.00;
    }
}

