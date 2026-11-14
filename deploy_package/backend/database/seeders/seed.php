<?php
declare(strict_types=1);

/**
 * P and P Pet Shop - Comprehensive Database Seeder
 * Usage via CLI: php backend/database/seeders/seed.php
 */

require_once __DIR__ . '/../../src/Autoloader.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\SecurityHelper;
use App\Helpers\StringHelper;

echo "=== P and P Pet Shop - Seeding Initial Database Records ===\n";

try {
    Config::load(__DIR__ . '/../../.env');
    $pdo = Database::getInstance()->getConnection();

    // 1. Seed Admin User
    $adminPassword = SecurityHelper::hashPassword('Admin@123');
    $stmt = $pdo->prepare("
        INSERT INTO admin_users (username, email, password_hash, full_name, role, is_active)
        VALUES ('admin', 'admin@pandppetshop.lk', :pass, 'P&P Store Manager', 'SUPER_ADMIN', 1)
        ON DUPLICATE KEY UPDATE full_name = VALUES(full_name)
    ");
    $stmt->execute(['pass' => $adminPassword]);
    echo "✓ Default admin user verified (username: admin / pass: Admin@123)\n";

    // 2. Seed Categories
    $categories = [
        ['name' => 'Pet Foods', 'slug' => 'foods', 'description' => 'Nutritious foods for dogs, cats, birds and small pets from leading global and local brands.', 'image_url' => '/assets/covers/foods-cover.jpg', 'sort' => 1],
        ['name' => 'Medicines & Care', 'slug' => 'medicines', 'description' => 'Veterinary-approved vitamins, supplements, flea & tick prevention, and wellness care.', 'image_url' => '/assets/covers/medicines-cover.jpg', 'sort' => 2],
        ['name' => 'Accessories & Toys', 'slug' => 'accessories', 'description' => 'Collars, leashes, harnesses, beds, chew toys, grooming kits and feeding bowls.', 'image_url' => '/assets/covers/accessories-cover.jpg', 'sort' => 3],
        ['name' => 'Dogs & Puppies', 'slug' => 'dogs', 'description' => 'Everything tailored for puppy wellness, active dogs, nutrition and training.', 'image_url' => '/assets/covers/dogs-cover.jpg', 'sort' => 4],
        ['name' => 'Cats & Kittens', 'slug' => 'cats', 'description' => 'Cat litter, scratching poles, kitten treats, dry and wet food for your feline companions.', 'image_url' => '/assets/covers/cats-cover.jpg', 'sort' => 5],
        ['name' => 'Birds & Parrots', 'slug' => 'birds', 'description' => 'Quality seeds, calcium blocks, swings, cage accessories and bird vitamins.', 'image_url' => '/assets/covers/birds-cover.jpg', 'sort' => 6],
        ['name' => 'Small Pets', 'slug' => 'small-pets', 'description' => 'Food, bedding, cages and accessories for hamsters, guinea pigs and rabbits.', 'image_url' => '/assets/covers/hamsters-cover.jpg', 'sort' => 7],
    ];

    $catMap = [];
    $catStmt = $pdo->prepare("
        INSERT INTO categories (name, slug, description, image_url, sort_order)
        VALUES (:name, :slug, :desc, :img, :sort)
        ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), image_url = VALUES(image_url)
    ");

    foreach ($categories as $cat) {
        $catStmt->execute([
            'name' => $cat['name'],
            'slug' => $cat['slug'],
            'desc' => $cat['description'],
            'img' => $cat['image_url'],
            'sort' => $cat['sort'],
        ]);
        
        $fetchId = $pdo->prepare("SELECT id FROM categories WHERE slug = :s LIMIT 1");
        $fetchId->execute(['s' => $cat['slug']]);
        $catMap[$cat['slug']] = (int)$fetchId->fetchColumn();
    }
    echo "✓ Categories seeded.\n";

    // 3. Seed Realistic Products
    $products = [
        [
            'category' => 'foods',
            'name' => 'Drools Focus Adult Super Premium Dog Food',
            'slug' => 'drools-focus-adult-super-premium-dog-food',
            'sku' => 'PNP-FOOD-1001',
            'short' => 'Super premium adult dog food with real chicken and wholesome egg for optimal muscle development.',
            'desc' => 'Drools Focus Adult Super Premium Dog Food is formulated with pure chicken and essential Omega 3 & 6 fatty acids to maintain vibrant coat health, strong joints and high stamina. Ideal for medium and large breed dogs in tropical Sri Lankan climates.',
            'base_price' => 7200.00,
            'sale_price' => 6850.00,
            'image' => '/assets/foods/10003.jpg',
            'variants' => [
                ['name' => '1.2 kg Pack', 'value' => '1.2kg', 'add_price' => 0.00, 'stock' => 25],
                ['name' => '4 kg Bag', 'value' => '4kg', 'add_price' => 6500.00, 'stock' => 15],
                ['name' => '12 kg Value Bag', 'value' => '12kg', 'add_price' => 18500.00, 'stock' => 8],
            ],
            'featured' => 1,
            'promotional' => 1,
        ],
        [
            'category' => 'foods',
            'name' => 'Royal Canin Mini Adult Wet Gravy Pouch',
            'slug' => 'royal-canin-mini-adult-wet-gravy-pouch',
            'sku' => 'PNP-FOOD-1002',
            'short' => 'Specially crafted wet gravy food for small breed adult dogs up to 10kg.',
            'desc' => 'Supports digestive health and maintains ideal weight for active small breed dogs. Highly palatable chunks in rich gravy that entice even the finickiest eaters.',
            'base_price' => 650.00,
            'sale_price' => null,
            'image' => '/assets/foods/10006.jpg',
            'variants' => [
                ['name' => 'Single 85g Pouch', 'value' => '85g', 'add_price' => 0.00, 'stock' => 50],
                ['name' => 'Pack of 12 Pouches', 'value' => '12-Pack', 'add_price' => 6500.00, 'stock' => 12],
            ],
            'featured' => 1,
            'promotional' => 0,
        ],
        [
            'category' => 'foods',
            'name' => 'Me-O Tuna Adult Cat Dry Food',
            'slug' => 'me-o-tuna-adult-cat-dry-food',
            'sku' => 'PNP-FOOD-1003',
            'short' => 'Complete and balanced nutrition with delicious ocean tuna flavor.',
            'desc' => 'Me-O Tuna Cat Food is fortified with taurine for sharp vision, vitamin C to boost immune health, and low-sodium formulation to protect kidney wellness.',
            'base_price' => 3200.00,
            'sale_price' => 2950.00,
            'image' => '/assets/foods/10010.jpg',
            'variants' => [
                ['name' => '1.1 kg Bag', 'value' => '1.1kg', 'add_price' => 0.00, 'stock' => 30],
                ['name' => '3 kg Economy Bag', 'value' => '3kg', 'add_price' => 4500.00, 'stock' => 18],
            ],
            'featured' => 1,
            'promotional' => 1,
        ],
        [
            'category' => 'medicines',
            'name' => 'Bayer Bolfo Anti-Tick & Flea Powder',
            'slug' => 'bayer-bolfo-anti-tick-and-flea-powder',
            'sku' => 'PNP-MED-2001',
            'short' => 'Fast-acting veterinary powder to eradicate ticks, fleas, and lice on dogs and cats.',
            'desc' => 'Propoxur 1% anti-parasitic powder safe for routine dusting. Helps prevent tick-borne diseases and eliminates skin irritation caused by external parasites.',
            'base_price' => 1450.00,
            'sale_price' => 1350.00,
            'image' => '/assets/medicines/10003.jpg',
            'variants' => [
                ['name' => '100g Dispenser Bottle', 'value' => '100g', 'add_price' => 0.00, 'stock' => 40],
            ],
            'featured' => 1,
            'promotional' => 0,
        ],
        [
            'category' => 'medicines',
            'name' => 'Pet-Tabs Plus Daily Vitamin & Mineral Supplement',
            'slug' => 'pet-tabs-plus-daily-vitamin-mineral-supplement',
            'sku' => 'PNP-MED-2002',
            'short' => 'Comprehensive daily multivitamin chewables for active, growing, and senior dogs.',
            'desc' => 'Rich in Calcium, Zinc, Vitamin A, B-Complex, and Iron. Promotes healthy joints, high vitality, and resilient immune defense for dogs in all life stages.',
            'base_price' => 4800.00,
            'sale_price' => null,
            'image' => '/assets/medicines/10007.jpg',
            'variants' => [
                ['name' => '60 Chewable Tablets', 'value' => '60-Tabs', 'add_price' => 0.00, 'stock' => 20],
                ['name' => '180 Chewable Tablets', 'value' => '180-Tabs', 'add_price' => 7500.00, 'stock' => 10],
            ],
            'featured' => 0,
            'promotional' => 0,
        ],
        [
            'category' => 'accessories',
            'name' => 'Heavy-Duty Reflective Comfort Dog Harness',
            'slug' => 'heavy-duty-reflective-comfort-dog-harness',
            'sku' => 'PNP-ACC-3001',
            'short' => 'No-pull breathable mesh harness with 3M reflective safety stitching.',
            'desc' => 'Padded ergonomic design that distributes pulling pressure evenly across chest and shoulders without choking. Features sturdy dual D-rings and top safety handle.',
            'base_price' => 3800.00,
            'sale_price' => 3400.00,
            'image' => '/assets/accessories/10003.jpg',
            'variants' => [
                ['name' => 'Size Small (Chest 45-55cm)', 'value' => 'S', 'add_price' => 0.00, 'stock' => 15],
                ['name' => 'Size Medium (Chest 55-70cm)', 'value' => 'M', 'add_price' => 400.00, 'stock' => 20],
                ['name' => 'Size Large (Chest 70-90cm)', 'value' => 'L', 'add_price' => 800.00, 'stock' => 15],
            ],
            'featured' => 1,
            'promotional' => 1,
        ],
        [
            'category' => 'accessories',
            'name' => 'Non-Slip Stainless Steel Pet Food & Water Bowl',
            'slug' => 'non-slip-stainless-steel-pet-food-water-bowl',
            'sku' => 'PNP-ACC-3002',
            'short' => 'Rust-proof hygienic stainless steel bowl with silicone non-slip rubber base.',
            'desc' => 'Dishwasher safe, durable, and odor-resistant food bowl engineered to prevent tipping and sliding during vigorous meal times.',
            'base_price' => 1200.00,
            'sale_price' => null,
            'image' => '/assets/accessories/10008.jpg',
            'variants' => [
                ['name' => 'Small 400ml', 'value' => '400ml', 'add_price' => 0.00, 'stock' => 35],
                ['name' => 'Medium 850ml', 'value' => '850ml', 'add_price' => 500.00, 'stock' => 30],
                ['name' => 'Large 1600ml', 'value' => '1600ml', 'add_price' => 1100.00, 'stock' => 25],
            ],
            'featured' => 0,
            'promotional' => 0,
        ],
        [
            'category' => 'birds',
            'name' => 'Vitakraft Australian Budgie & Parrot Seed Mix',
            'slug' => 'vitakraft-australian-budgie-parrot-seed-mix',
            'sku' => 'PNP-BIRD-4001',
            'short' => 'Nutritionally balanced seed and grain blend with eucalyptus leaves and honey.',
            'desc' => 'Species-appropriate seed mixture enriched with essential vitamins, minerals, and iodine pearls to support healthy thyroid function and brilliant feathering.',
            'base_price' => 2600.00,
            'sale_price' => 2350.00,
            'image' => '/assets/birds/10003.jpg',
            'variants' => [
                ['name' => '1 kg Box', 'value' => '1kg', 'add_price' => 0.00, 'stock' => 25],
            ],
            'featured' => 1,
            'promotional' => 1,
        ],
        [
            'category' => 'small-pets',
            'name' => 'Crispy Muesli Complete Hamster & Guinea Pig Food',
            'slug' => 'crispy-muesli-complete-hamster-guinea-pig-food',
            'sku' => 'PNP-HAM-5001',
            'short' => 'Tasty fiber-rich muesli mixture with puffed grains, vegetables and essential vitamin C.',
            'desc' => 'High quality muesli blend containing all vital nutrients, cereals, and vegetables needed for digestive health and tooth wear in hamsters, mice, and gerbils.',
            'base_price' => 2100.00,
            'sale_price' => 1950.00,
            'image' => '/assets/hamsters/10003.jpg',
            'variants' => [
                ['name' => '1 kg Bag', 'value' => '1kg', 'add_price' => 0.00, 'stock' => 20],
                ['name' => '2.5 kg Bag', 'value' => '2.5kg', 'add_price' => 2400.00, 'stock' => 10],
            ],
            'featured' => 1,
            'promotional' => 0,
        ]
    ];

    $prodStmt = $pdo->prepare("
        INSERT INTO products (category_id, name, slug, sku, description, short_description, base_price, sale_price, currency, is_active, is_featured, is_promotional)
        VALUES (:cat_id, :name, :slug, :sku, :desc, :short, :base, :sale, 'LKR', 1, :feat, :promo)
        ON DUPLICATE KEY UPDATE base_price = VALUES(base_price), sale_price = VALUES(sale_price), is_featured = VALUES(is_featured), is_promotional = VALUES(is_promotional)
    ");

    $imgStmt = $pdo->prepare("
        INSERT INTO product_images (product_id, image_url, is_primary, sort_order)
        VALUES (:p_id, :url, 1, 0)
        ON DUPLICATE KEY UPDATE image_url = VALUES(image_url)
    ");

    $varStmt = $pdo->prepare("
        INSERT INTO product_variants (product_id, sku, variant_name, option_name, option_value, additional_price, stock_quantity, is_active)
        VALUES (:p_id, :sku, :v_name, 'Option', :val, :add_price, :stock, 1)
        ON DUPLICATE KEY UPDATE additional_price = VALUES(additional_price), stock_quantity = VALUES(stock_quantity)
    ");

    $invStmt = $pdo->prepare("
        INSERT INTO inventory (product_id, variant_id, quantity_on_hand, quantity_reserved, reorder_level)
        VALUES (:p_id, :v_id, :qty, 0, 5)
        ON DUPLICATE KEY UPDATE quantity_on_hand = VALUES(quantity_on_hand)
    ");

    foreach ($products as $p) {
        $catId = $catMap[$p['category']] ?? 1;
        $prodStmt->execute([
            'cat_id' => $catId,
            'name' => $p['name'],
            'slug' => $p['slug'],
            'sku' => $p['sku'],
            'desc' => $p['desc'],
            'short' => $p['short'],
            'base' => $p['base_price'],
            'sale' => $p['sale_price'],
            'feat' => $p['featured'],
            'promo' => $p['promotional'],
        ]);

        $pIdStmt = $pdo->prepare("SELECT id FROM products WHERE sku = :s LIMIT 1");
        $pIdStmt->execute(['s' => $p['sku']]);
        $productId = (int)$pIdStmt->fetchColumn();

        // Primary Image
        $imgStmt->execute(['p_id' => $productId, 'url' => $p['image']]);

        $totalProductStock = 0;
        foreach ($p['variants'] as $vIndex => $v) {
            $variantSku = $p['sku'] . '-V' . ($vIndex + 1);
            $varStmt->execute([
                'p_id' => $productId,
                'sku' => $variantSku,
                'v_name' => $v['name'],
                'val' => $v['value'],
                'add_price' => $v['add_price'],
                'stock' => $v['stock'],
            ]);

            $vIdStmt = $pdo->prepare("SELECT id FROM product_variants WHERE sku = :vs LIMIT 1");
            $vIdStmt->execute(['vs' => $variantSku]);
            $variantId = (int)$vIdStmt->fetchColumn();

            $invStmt->execute([
                'p_id' => $productId,
                'v_id' => $variantId,
                'qty' => $v['stock'],
            ]);

            $totalProductStock += $v['stock'];
        }

        // Base inventory record without variant
        $invStmt->execute([
            'p_id' => $productId,
            'v_id' => null,
            'qty' => $totalProductStock,
        ]);
    }
    echo "✓ Products, variants, images, and inventory seeded.\n";

    // 4. Seed Uber Store and Integration Settings
    $pdo->exec("
        INSERT INTO uber_stores (uber_store_id, store_name, status, address)
        VALUES ('store-pandp-mt-lavinia', 'P and P Pet Shop - Mount Lavinia', 'ACTIVE', 'Galle Road, Mount Lavinia, Sri Lanka')
        ON DUPLICATE KEY UPDATE store_name = VALUES(store_name)
    ");

    $pdo->exec("
        INSERT INTO integration_settings (setting_group, setting_key, setting_value, description)
        VALUES 
            ('UBER', 'uber_sync_catalog_interval', '30', 'Minutes between catalog synchronization jobs'),
            ('UBER', 'uber_sync_price_interval', '15', 'Minutes between price synchronization jobs'),
            ('UBER', 'uber_sync_inventory_interval', '10', 'Minutes between inventory synchronization jobs'),
            ('UBER', 'uber_external_order_injection_approved', '0', 'Flag indicating if Uber merchant POS order injection is approved by Uber'),
            ('GENERAL', 'store_phone', '+94112712345', 'Customer support hotline'),
            ('GENERAL', 'store_address', 'Mount Lavinia, Sri Lanka', 'Physical store address'),
            ('GENERAL', 'default_currency', 'LKR', 'Default shop currency')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    echo "✓ Integration settings and Uber store initialized.\n";

    echo "=== Database Seeding Completed Successfully! ===\n";

} catch (Throwable $e) {
    echo "✗ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
