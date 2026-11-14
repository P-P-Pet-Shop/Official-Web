<?php
declare(strict_types=1);

namespace App\Core;

class Config
{
    private static array $settings = [];
    private static bool $loaded = false;

    public static function load(?string $envFile = null): void
    {
        if (self::$loaded) {
            return;
        }

        // Set default configuration
        self::$settings = [
            'app_env' => 'production',
            'app_debug' => false,
            'app_url' => 'https://pandppetshop.lk',
            'api_prefix' => '/api/v1',
            'currency' => 'LKR',
            'currency_symbol' => 'Rs.',
            'timezone' => 'Asia/Colombo',
            
            // Database defaults
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_name' => 'pandp_petshop',
            'db_user' => 'root',
            'db_pass' => '',
            'db_charset' => 'utf8mb4',

            // Uber Eats Configuration
            'uber_client_id' => '',
            'uber_client_secret' => '',
            'uber_redirect_uri' => '',
            'uber_store_id' => '',
            'uber_environment' => 'sandbox', // or 'production'
            'uber_access_token' => '',
            'uber_refresh_token' => '',
            'uber_webhook_signing_key' => '',

            // Security
            'jwt_secret' => 'change-me-to-a-very-secure-random-key',
            'session_lifetime' => 7200,
            'cors_origins' => '*',
            'rate_limit_per_minute' => 60,
        ];

        // Parse .env if provided and exists
        $filePath = $envFile ?? __DIR__ . '/../../.env';
        if (file_exists($filePath)) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (str_contains($line, '=')) {
                        [$key, $val] = explode('=', $line, 2);
                        $key = strtolower(trim($key));
                        $val = trim($val, " \t\n\r\0\x0B\"'");
                        self::$settings[$key] = $val;
                    }
                }
            }
        }

        // Allow system environment variables to override
        foreach (self::$settings as $k => $v) {
            $envVal = getenv(strtoupper($k));
            if ($envVal !== false) {
                self::$settings[$k] = $envVal;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        $normalizedKey = strtolower($key);
        return self::$settings[$normalizedKey] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::load();
        self::$settings[strtolower($key)] = $value;
    }

    public static function isDebug(): bool
    {
        $val = self::get('app_debug', false);
        return $val === true || $val === 'true' || $val === '1' || $val === 1;
    }
}

