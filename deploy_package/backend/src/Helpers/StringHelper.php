<?php
declare(strict_types=1);

namespace App\Helpers;

class StringHelper
{
    public static function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        return empty($text) ? 'item-' . bin2hex(random_bytes(3)) : $text;
    }

    public static function generateOrderNumber(): string
    {
        // Format: PNP-YYYYMMDD-XXXX (e.g. PNP-20261113-A78K)
        $date = gmdate('Ymd');
        $random = strtoupper(bin2hex(random_bytes(2)));
        return "PNP-{$date}-{$random}";
    }

    public static function generateTrackingToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function generateIdempotencyKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function sanitize(string $text): string
    {
        return htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
