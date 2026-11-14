<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Config;

class CurrencyHelper
{
    public static function format(float|int|string $amount, bool $showSymbol = true): string
    {
        $num = (float)$amount;
        $formatted = number_format($num, 2, '.', ',');
        if (!$showSymbol) {
            return $formatted;
        }
        $symbol = Config::get('currency_symbol', 'Rs.');
        return $symbol . ' ' . $formatted;
    }

    public static function toDecimal(mixed $amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }

    public static function getCurrencyCode(): string
    {
        return Config::get('currency', 'LKR');
    }
}
