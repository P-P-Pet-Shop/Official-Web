<?php
declare(strict_types=1);

use App\Helpers\CurrencyHelper;

class CurrencyHelperTest
{
    private TestRunner $runner;

    public function __construct(TestRunner $runner)
    {
        $this->runner = $runner;
    }

    public function execute(): void
    {
        // 1. Format with default symbol
        $formatted = CurrencyHelper::format(2500);
        $this->runner->assert(str_contains($formatted, '2,500.00'), 'Formats integer amount with two decimals');
        $this->runner->assert(str_starts_with($formatted, 'Rs.'), 'Formats with Sri Lankan Rupee Rs. prefix');

        // 2. Format large decimal
        $largeFormatted = CurrencyHelper::format(1450000.50);
        $this->runner->assert(str_contains($largeFormatted, '1,450,000.50'), 'Formats large numbers with commas');

        // 3. Without symbol
        $noSymbol = CurrencyHelper::format(6850.75, false);
        $this->runner->assert($noSymbol === '6,850.75', 'Formats without currency symbol');

        // 4. toDecimal
        $decimal = CurrencyHelper::toDecimal('450.9');
        $this->runner->assert($decimal === '450.90', 'Converts string float to exact 2-decimal string');
    }
}

