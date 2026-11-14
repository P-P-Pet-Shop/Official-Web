<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ProductRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findPaginated(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = ['p.is_active = 1'];
        $params = [];

        if (!empty($filters['category'])) {
            $where[] = '(c.slug = :category_slug OR c.id = :category_id)';
            $params['category_slug'] = $filters['category'];
            $params['category_id'] = is_numeric($filters['category']) ? (int)$filters['category'] : 0;
        }

        if (!empty($filters['search'])) {
            $where[] = '(p.name LIKE :search OR p.description LIKE :search OR p.sku LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['featured'])) {
            $where[] = 'p.is_featured = 1';
        }

        if (!empty($filters['promotional'])) {
            $where[] = 'p.is_promotional = 1';
        }

        $whereClause = implode(' AND ', $where);

        // Sorting
        $orderBy = match ($filters['sort'] ?? 'latest') {
            'price_asc' => 'COALESCE(p.sale_price, p.base_price) ASC',
            'price_desc' => 'COALESCE(p.sale_price, p.base_price) DESC',
            'name_asc' => 'p.name ASC',
            'name_desc' => 'p.name DESC',
            default => 'p.is_featured DESC, p.created_at DESC',
        };

        // Total count
        $countSql = "SELECT COUNT(*) as total 
                     FROM products p 
                     JOIN categories c ON p.category_id = c.id 
                     WHERE {$whereClause}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Data query
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug,
                       (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image,
                       COALESCE((SELECT SUM(quantity_on_hand - quantity_reserved) FROM inventory WHERE product_id = p.id), 0) as total_stock
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE {$whereClause}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ]
        ];
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug,
                   COALESCE((SELECT SUM(quantity_on_hand - quantity_reserved) FROM inventory WHERE product_id = p.id), 0) as total_stock
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.slug = :slug AND p.is_active = 1
            LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);
        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }

        // Attach images
        $imgStmt = $this->db->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, sort_order ASC");
        $imgStmt->execute(['id' => $product['id']]);
        $product['images'] = $imgStmt->fetchAll();

        // Attach variants
        $varStmt = $this->db->prepare("
            SELECT v.*, 
                   COALESCE(i.quantity_on_hand - i.quantity_reserved, 0) as available_stock
            FROM product_variants v
            LEFT JOIN inventory i ON v.id = i.variant_id
            WHERE v.product_id = :id AND v.is_active = 1
            ORDER BY v.id ASC
        ");
        $varStmt->execute(['id' => $product['id']]);
        $product['variants'] = $varStmt->fetchAll();

        return $product;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }

        $imgStmt = $this->db->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, sort_order ASC");
        $imgStmt->execute(['id' => $id]);
        $product['images'] = $imgStmt->fetchAll();

        $varStmt = $this->db->prepare("SELECT * FROM product_variants WHERE product_id = :id ORDER BY id ASC");
        $varStmt->execute(['id' => $id]);
        $product['variants'] = $varStmt->fetchAll();

        return $product;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO products (category_id, name, slug, sku, description, short_description, base_price, sale_price, currency, is_active, is_featured, is_promotional, track_inventory)
            VALUES (:category_id, :name, :slug, :sku, :description, :short_description, :base_price, :sale_price, :currency, :is_active, :is_featured, :is_promotional, :track_inventory)
        ");

        $stmt->execute([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'sku' => $data['sku'],
            'description' => $data['description'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'base_price' => $data['base_price'],
            'sale_price' => $data['sale_price'] ?? null,
            'currency' => $data['currency'] ?? 'LKR',
            'is_active' => $data['is_active'] ?? 1,
            'is_featured' => $data['is_featured'] ?? 0,
            'is_promotional' => $data['is_promotional'] ?? 0,
            'track_inventory' => $data['track_inventory'] ?? 1,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $val) {
            if (in_array($key, ['category_id', 'name', 'slug', 'sku', 'description', 'short_description', 'base_price', 'sale_price', 'currency', 'is_active', 'is_featured', 'is_promotional', 'track_inventory'], true)) {
                $fields[] = "`{$key}` = :{$key}";
                $params[$key] = $val;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}

