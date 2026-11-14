# Database Schema Specification
## P and P Pet Shop Database (MySQL / MariaDB)

The database schema is fully relational, uses the InnoDB engine, utf8mb4 encoding, and strict foreign keys with cascading where appropriate.

---

### Entity Relationship & Table Summary (24 Tables)

| # | Table Name | Purpose | Key Relationships |
|---|---|---|---|
| 1 | `admin_users` | Store administrative staff and management logins | Auth & audit logs |
| 2 | `customers` | Registered website customer profiles | 1:M with orders & addresses |
| 3 | `customer_addresses` | Saved customer delivery locations in Sri Lanka | Belongs to `customers` |
| 4 | `categories` | Product hierarchy (Foods, Medicines, etc.) | Self-referencing (parent_id) |
| 5 | `products` | Main product records, prices, and slugs | Belongs to `categories` |
| 6 | `product_images` | Multiple gallery images per product | Belongs to `products` |
| 7 | `product_variants` | Product options (package size, weight, dimensions) | Belongs to `products` |
| 8 | `inventory` | Real-time stock levels, reserved units, reorder levels | 1:1 with `products` / `variants` |
| 9 | `carts` | Persistent shopping carts for guest and logged-in users | Has many `cart_items` |
| 10 | `cart_items` | Individual items inside active carts | Belongs to `carts` & `products` |
| 11 | `orders` | Master orders table with financial & status snapshots | Has many items, events, payments |
| 12 | `order_items` | Immutable item records at the time of purchase | Belongs to `orders` |
| 13 | `order_events` | Complete audit trail of order status transitions | Belongs to `orders` |
| 14 | `payments` | Payment transactions (COD, Bank Transfer, Card) | Belongs to `orders` |
| 15 | `deliveries` | Dispatch, courier tracking, and delivery logs | Belongs to `orders` |
| 16 | `uber_stores` | Uber Eats merchant store metadata | Keyed by `uber_store_id` |
| 17 | `uber_product_mappings` | Association between website and Uber items | Maps internal IDs to Uber item IDs |
| 18 | `uber_sync_logs` | Audit of all catalog, price, and inventory syncs | Stores counts & failure reasons |
| 19 | `uber_webhook_events` | Log of raw incoming Uber webhook notifications | Tracks processing status |
| 20 | `integration_settings` | Encrypted keys, store configuration, sync intervals | Key-value store |
| 21 | `api_logs` | Client IP, endpoint latency, status codes | Security & performance analytics |
| 22 | `audit_logs` | Administrative changes to products/orders | Accountability trail |
| 23 | `cron_locks` | Database locks to prevent overlapping cron runs | Lock key with TTL |
| 24 | `notification_logs` | SMS/Email customer notification tracking | Outbound communications |

---

### Detailed Table Definitions

#### 1. `admin_users`
```sql
CREATE TABLE `admin_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('SUPER_ADMIN', 'MANAGER', 'STAFF') NOT NULL DEFAULT 'STAFF',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 5. `products`
```sql
CREATE TABLE `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `sku` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `short_description` VARCHAR(500) NULL,
  `base_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sale_price` DECIMAL(12,2) NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'LKR',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_promotional` TINYINT(1) NOT NULL DEFAULT 0,
  `track_inventory` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
);
```

#### 11. `orders`
```sql
CREATE TABLE `orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `customer_id` INT UNSIGNED NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_email` VARCHAR(100) NULL,
  `customer_phone` VARCHAR(25) NOT NULL,
  `delivery_address` TEXT NOT NULL,
  `delivery_city` VARCHAR(100) NOT NULL,
  `delivery_district` VARCHAR(100) NOT NULL DEFAULT 'Colombo',
  `delivery_instructions` TEXT NULL,
  `payment_method` ENUM('CASH_ON_DELIVERY', 'BANK_TRANSFER', 'ONLINE_CARD') NOT NULL DEFAULT 'CASH_ON_DELIVERY',
  `payment_status` ENUM('PENDING', 'PAID', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
  `website_order_status` ENUM('PENDING', 'VALIDATING', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP', 'PICKED_UP', 'DELIVERED', 'REJECTED', 'CANCELLED', 'INTEGRATION_FAILED') NOT NULL DEFAULT 'PENDING',
  `uber_order_status` VARCHAR(50) NOT NULL DEFAULT 'NOT_INTEGRATED',
  `delivery_status` VARCHAR(50) NOT NULL DEFAULT 'PENDING',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'LKR',
  `idempotency_key` VARCHAR(64) NOT NULL UNIQUE,
  `uber_order_id` VARCHAR(100) NULL,
  `tracking_token` VARCHAR(64) NOT NULL UNIQUE,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 17. `uber_product_mappings`
```sql
CREATE TABLE `uber_product_mappings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NULL,
  `uber_item_id` VARCHAR(100) NOT NULL,
  `uber_category_id` VARCHAR(100) NULL,
  `uber_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `uber_is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `sync_status` ENUM('SYNCED', 'PENDING', 'FAILED', 'EXCLUDED') NOT NULL DEFAULT 'PENDING',
  `last_synced_at` DATETIME NULL,
  `sync_error` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_uber_item` (`uber_item_id`)
);
```

---

### Monetary & Storage Rules

- **Currency**: All monetary columns (`base_price`, `sale_price`, `subtotal`, `delivery_fee`, `total_amount`) use `DECIMAL(12,2)` to eliminate floating point rounding inaccuracies.
- **Timestamps**: All dates are stored in UTC (`DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`). Local presentation defaults to `Asia/Colombo` (+05:30).
- **Snapshot Integrity**: When an order is placed, `order_items` preserves product title, selected variant name, SKU, and unit price as an immutable snapshot. Future changes to catalogue pricing never alter historic order totals.

