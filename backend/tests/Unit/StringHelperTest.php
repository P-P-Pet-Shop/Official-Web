<?php
declare(strict_types=1);

use App\Helpers\StringHelper;

class StringHelperTest
{
    private TestRunner $runner;

    public function __construct(TestRunner $runner)
    {
        $this->runner = $runner;
    }

    public function execute(): void
    {
        // 1. Slugify
        $slug = StringHelper::slugify('Royal Canin Adult Dog Food (1.2kg)!');
        $this->runner->assert($slug === 'royal-canin-adult-dog-food-1-2kg', 'Creates valid URL slug from product title');

        // 2. Order number format
        $orderNum = StringHelper::generateOrderNumber();
        $this->runner->assert((bool)preg_match('/^PNP-\d{8}-[A-F0-9]{4}$/', $orderNum), 'Generates valid order number pattern PNP-YYYYMMDD-XXXX');

        // 3. Tracking token
        $token = StringHelper::generateTrackingToken();
        $this->runner->assert(strlen($token) === 64, 'Generates 64-char hex tracking token');

        // 4. Sanitize
        $clean = StringHelper::sanitize("<script>alert('xss')</script> Mount Lavinia");
        $this->runner->assert(!str_contains($clean, '<script>'), 'Sanitizes dangerous HTML tags');
    }
}

