-- ==============================================================================
-- P and P Pet Shop - Complete Database Setup (Schema + Initial Seed)
-- Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci | Engine: InnoDB
-- Compatible with MySQL 8.0+ / MariaDB 10.5+
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- 1. Admin Users
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('SUPER_ADMIN', 'MANAGER', 'STAFF') NOT NULL DEFAULT 'STAFF',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_admin_username` (`username`),
  INDEX `idx_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `password_hash` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `email_verified_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_customer_email` (`email`),
  INDEX `idx_customer_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Customer Addresses
CREATE TABLE IF NOT EXISTS `customer_addresses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `address_title` VARCHAR(50) NOT NULL DEFAULT 'Home',
  `recipient_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `address_line1` VARCHAR(255) NOT NULL,
  `address_line2` VARCHAR(255) NULL,
  `city` VARCHAR(100) NOT NULL,
  `district` VARCHAR(100) NOT NULL DEFAULT 'Colombo',
  `postal_code` VARCHAR(20) NULL,
  `delivery_instructions` TEXT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  INDEX `idx_address_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `image_url` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  INDEX `idx_category_slug` (`slug`),
  INDEX `idx_category_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Products
CREATE TABLE IF NOT EXISTS `products` (
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
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  INDEX `idx_product_slug` (`slug`),
  INDEX `idx_product_sku` (`sku`),
  INDEX `idx_product_category` (`category_id`),
  INDEX `idx_product_active_featured` (`is_active`, `is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Product Images
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(255) NOT NULL,
  `alt_text` VARCHAR(150) NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  INDEX `idx_image_product` (`product_id`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Product Variants
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `sku` VARCHAR(50) NOT NULL UNIQUE,
  `variant_name` VARCHAR(100) NOT NULL,
  `option_name` VARCHAR(50) NOT NULL DEFAULT 'Size',
  `option_value` VARCHAR(50) NOT NULL,
  `additional_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  INDEX `idx_variant_product` (`product_id`),
  INDEX `idx_variant_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Inventory
CREATE TABLE IF NOT EXISTS `inventory` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NULL,
  `quantity_on_hand` INT NOT NULL DEFAULT 0,
  `quantity_reserved` INT NOT NULL DEFAULT 0,
  `reorder_level` INT NOT NULL DEFAULT 5,
  `low_stock_threshold` INT NOT NULL DEFAULT 2,
  `last_restocked_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_inventory_item` (`product_id`, `variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Carts
CREATE TABLE IF NOT EXISTS `carts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(100) NOT NULL,
  `customer_id` INT UNSIGNED NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  UNIQUE KEY `uk_cart_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Cart Items
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cart_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price_snapshot` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  UNIQUE KEY `uk_cart_product_variant` (`cart_id`, `product_id`, `variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Orders
CREATE TABLE IF NOT EXISTS `orders` (
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
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  INDEX `idx_order_number` (`order_number`),
  INDEX `idx_order_status` (`website_order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Order Items
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `variant_name` VARCHAR(100) NULL,
  `sku` VARCHAR(50) NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  INDEX `idx_order_items_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Order Events
CREATE TABLE IF NOT EXISTS `order_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `previous_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `event_type` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `triggered_by` VARCHAR(100) NOT NULL DEFAULT 'SYSTEM',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Payments
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'LKR',
  `transaction_reference` VARCHAR(100) NULL,
  `payment_status` ENUM('PENDING', 'PAID', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
  `payment_gateway_response` TEXT NULL,
  `paid_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Deliveries
CREATE TABLE IF NOT EXISTS `deliveries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `delivery_provider` VARCHAR(50) NOT NULL DEFAULT 'INTERNAL',
  `provider_reference` VARCHAR(100) NULL,
  `courier_name` VARCHAR(100) NULL,
  `courier_phone` VARCHAR(25) NULL,
  `tracking_url` VARCHAR(255) NULL,
  `estimated_pickup_at` DATETIME NULL,
  `estimated_delivery_at` DATETIME NULL,
  `delivery_status` VARCHAR(50) NOT NULL DEFAULT 'PENDING',
  `delivery_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Uber Stores
CREATE TABLE IF NOT EXISTS `uber_stores` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `uber_store_id` VARCHAR(100) NOT NULL UNIQUE,
  `store_name` VARCHAR(150) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
  `address` TEXT NULL,
  `raw_store_data` LONGTEXT NULL,
  `last_synced_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Uber Product Mappings
CREATE TABLE IF NOT EXISTS `uber_product_mappings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Uber Sync Logs
CREATE TABLE IF NOT EXISTS `uber_sync_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sync_type` ENUM('CATALOG', 'PRICE', 'INVENTORY', 'ORDER') NOT NULL,
  `status` ENUM('STARTED', 'SUCCESS', 'FAILED') NOT NULL,
  `items_processed` INT NOT NULL DEFAULT 0,
  `items_updated` INT NOT NULL DEFAULT 0,
  `items_failed` INT NOT NULL DEFAULT 0,
  `error_details` TEXT NULL,
  `started_at` DATETIME NOT NULL,
  `completed_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Uber Webhook Events
CREATE TABLE IF NOT EXISTS `uber_webhook_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` VARCHAR(100) NOT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `status` ENUM('RECEIVED', 'PROCESSED', 'FAILED') NOT NULL DEFAULT 'RECEIVED',
  `error_message` TEXT NULL,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Integration Settings
CREATE TABLE IF NOT EXISTS `integration_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_group` VARCHAR(50) NOT NULL,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
  `description` VARCHAR(255) NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. API Logs
CREATE TABLE IF NOT EXISTS `api_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_ip` VARCHAR(45) NOT NULL,
  `method` VARCHAR(10) NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `request_headers` TEXT NULL,
  `request_body` LONGTEXT NULL,
  `response_status` INT NOT NULL,
  `response_body` LONGTEXT NULL,
  `execution_time_ms` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. Audit Logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_type` ENUM('ADMIN', 'CUSTOMER', 'SYSTEM') NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(100) NOT NULL,
  `entity_id` INT UNSIGNED NULL,
  `old_values` LONGTEXT NULL,
  `new_values` LONGTEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. Cron Locks
CREATE TABLE IF NOT EXISTS `cron_locks` (
  `lock_key` VARCHAR(50) PRIMARY KEY,
  `locked_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `pid` INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. Notification Logs
CREATE TABLE IF NOT EXISTS `notification_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `recipient_type` VARCHAR(50) NOT NULL DEFAULT 'CUSTOMER',
  `recipient` VARCHAR(100) NOT NULL,
  `notification_type` VARCHAR(50) NOT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `body` TEXT NOT NULL,
  `status` ENUM('SENT', 'FAILED', 'PENDING') NOT NULL DEFAULT 'PENDING',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- INITIAL DATA SEEDS
-- ==============================================================================

-- Admin User: admin / Admin@123
INSERT INTO `admin_users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`)
VALUES (1, 'admin', 'admin@pandppetshop.lk', '$2y$12$K89eG19wM58E6y6Jj1k0s.L07zV5m7Yt3U9s6W8B3F2q9j3m7r1tK', 'P&P Store Manager', 'SUPER_ADMIN', 1)
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image_url`, `sort_order`, `is_active`) VALUES
(1, 'Pet Foods', 'foods', 'Nutritious foods for dogs, cats, birds and small pets from leading global and local brands.', '/assets/covers/foods-cover.jpg', 1, 1),
(2, 'Medicines & Care', 'medicines', 'Veterinary-approved vitamins, supplements, flea & tick prevention, and wellness care.', '/assets/covers/medicines-cover.jpg', 2, 1),
(3, 'Accessories & Toys', 'accessories', 'Collars, leashes, harnesses, beds, chew toys, grooming kits and feeding bowls.', '/assets/covers/accessories-cover.jpg', 3, 1),
(4, 'Dogs & Puppies', 'dogs', 'Everything tailored for puppy wellness, active dogs, nutrition and training.', '/assets/covers/dogs-cover.jpg', 4, 1),
(5, 'Cats & Kittens', 'cats', 'Cat litter, scratching poles, kitten treats, dry and wet food for your feline companions.', '/assets/covers/cats-cover.jpg', 5, 1),
(6, 'Birds & Parrots', 'birds', 'Quality seeds, calcium blocks, swings, cage accessories and bird vitamins.', '/assets/covers/birds-cover.jpg', 6, 1),
(7, 'Small Pets', 'small-pets', 'Food, bedding, cages and accessories for hamsters, guinea pigs and rabbits.', '/assets/covers/hamsters-cover.jpg', 7, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Products
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `sku`, `description`, `short_description`, `base_price`, `sale_price`, `currency`, `is_active`, `is_featured`, `is_promotional`) VALUES
(1, 1, 'Drools Focus Adult Super Premium Dog Food', 'drools-focus-adult-super-premium-dog-food', 'PNP-FOOD-1001', 'Formulated with real chicken and wholesome egg for optimal muscle development and vibrant energy in tropical climates.', 'Super premium adult dog food with real chicken and wholesome egg.', 7200.00, 6850.00, 'LKR', 1, 1, 1),
(2, 1, 'Royal Canin Mini Adult Wet Gravy Pouch', 'royal-canin-mini-adult-wet-gravy-pouch', 'PNP-FOOD-1002', 'Supports digestive health and maintains ideal weight for active small breed dogs.', 'Specially crafted wet gravy food for small breed adult dogs up to 10kg.', 650.00, NULL, 'LKR', 1, 1, 0),
(3, 1, 'Me-O Tuna Adult Cat Dry Food', 'me-o-tuna-adult-cat-dry-food', 'PNP-FOOD-1003', 'Fortified with taurine for sharp vision, vitamin C to boost immunity, and kidney-friendly formula.', 'Complete and balanced nutrition with delicious ocean tuna flavor.', 3200.00, 2950.00, 'LKR', 1, 1, 1),
(4, 2, 'Bayer Bolfo Anti-Tick & Flea Powder', 'bayer-bolfo-anti-tick-and-flea-powder', 'PNP-MED-2001', 'Propoxur 1% anti-parasitic powder safe for routine dusting on dogs and cats.', 'Fast-acting veterinary powder to eradicate ticks, fleas, and lice.', 1450.00, 1350.00, 'LKR', 1, 0, 0),
(5, 2, 'Pet-Tabs Plus Daily Vitamin & Mineral Supplement', 'pet-tabs-plus-daily-vitamin-mineral-supplement', 'PNP-MED-2002', 'Rich in Calcium, Zinc, Vitamin A, B-Complex, and Iron for vitality and joint care.', 'Comprehensive daily multivitamin chewables for active and senior dogs.', 4800.00, NULL, 'LKR', 1, 1, 0),
(6, 3, 'Heavy-Duty Reflective Comfort Dog Harness', 'heavy-duty-reflective-comfort-dog-harness', 'PNP-ACC-3001', 'Padded ergonomic design that distributes pulling pressure without choking with 3M reflective trim.', 'No-pull breathable mesh harness with 3M reflective safety stitching.', 3800.00, 3400.00, 'LKR', 1, 1, 1),
(7, 3, 'Non-Slip Stainless Steel Pet Food & Water Bowl', 'non-slip-stainless-steel-pet-food-water-bowl', 'PNP-ACC-3002', 'Rust-proof hygienic stainless steel bowl with non-slip silicone base.', 'Rust-proof hygienic stainless steel bowl with non-slip silicone base.', 1200.00, NULL, 'LKR', 1, 0, 0),
(8, 6, 'Vitakraft Australian Budgie & Parrot Seed Mix', 'vitakraft-australian-budgie-parrot-seed-mix', 'PNP-BIRD-4001', 'Species-appropriate seed mixture enriched with vitamins, minerals, and iodine pearls.', 'Nutritionally balanced seed and grain blend with eucalyptus leaves.', 2600.00, 2350.00, 'LKR', 1, 1, 1),
(9, 7, 'Crispy Muesli Complete Hamster & Guinea Pig Food', 'crispy-muesli-complete-hamster-guinea-pig-food', 'PNP-HAM-5001', 'High quality muesli blend containing vital nutrients and puffed grains for healthy tooth wear.', 'Tasty fiber-rich muesli mixture with puffed grains and vegetables.', 2100.00, 1950.00, 'LKR', 1, 1, 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Product Images
INSERT INTO `product_images` (`product_id`, `image_url`, `is_primary`, `sort_order`) VALUES
(1, '/src/assets/foods/10003.jpg', 1, 0),
(2, '/src/assets/foods/10006.jpg', 1, 0),
(3, '/src/assets/foods/10010.jpg', 1, 0),
(4, '/src/assets/medicines/10003.jpg', 1, 0),
(5, '/src/assets/medicines/10007.jpg', 1, 0),
(6, '/src/assets/accessories/10003.jpg', 1, 0),
(7, '/src/assets/accessories/10008.jpg', 1, 0),
(8, '/src/assets/birds/10003.jpg', 1, 0),
(9, '/src/assets/hamsters/10003.jpg', 1, 0)
ON DUPLICATE KEY UPDATE `image_url` = VALUES(`image_url`);

-- Inventory Records
INSERT INTO `inventory` (`product_id`, `variant_id`, `quantity_on_hand`, `quantity_reserved`, `reorder_level`) VALUES
(1, NULL, 48, 0, 5),
(2, NULL, 62, 0, 10),
(3, NULL, 48, 0, 5),
(4, NULL, 40, 0, 5),
(5, NULL, 30, 0, 5),
(6, NULL, 50, 0, 5),
(7, NULL, 65, 0, 10),
(8, NULL, 25, 0, 5),
(9, NULL, 30, 0, 5)
ON DUPLICATE KEY UPDATE `quantity_on_hand` = VALUES(`quantity_on_hand`);

-- Uber Store & Integration Settings
INSERT INTO `uber_stores` (`uber_store_id`, `store_name`, `status`, `address`)
VALUES ('store-pandp-mt-lavinia', 'P and P Pet Shop - Mount Lavinia', 'ACTIVE', 'Galle Road, Mount Lavinia, Sri Lanka')
ON DUPLICATE KEY UPDATE `store_name` = VALUES(`store_name`);

INSERT INTO `integration_settings` (`setting_group`, `setting_key`, `setting_value`, `description`) VALUES
('UBER', 'uber_sync_catalog_interval', '30', 'Minutes between catalog synchronization jobs'),
('UBER', 'uber_sync_price_interval', '15', 'Minutes between price synchronization jobs'),
('UBER', 'uber_sync_inventory_interval', '10', 'Minutes between inventory synchronization jobs'),
('UBER', 'uber_external_order_injection_approved', '0', 'Flag indicating if Uber merchant POS order injection is approved by Uber'),
('GENERAL', 'store_phone', '+94112712345', 'Customer support hotline'),
('GENERAL', 'store_address', 'Mount Lavinia, Sri Lanka', 'Physical store address'),
('GENERAL', 'default_currency', 'LKR', 'Default shop currency')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

SET FOREIGN_KEY_CHECKS = 1;

