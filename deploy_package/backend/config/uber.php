<?php
declare(strict_types=1);

use App\Core\Config;

return [
    'environment' => Config::get('uber_environment', 'sandbox'),
    'client_id' => Config::get('uber_client_id', ''),
    'client_secret' => Config::get('uber_client_secret', ''),
    'redirect_uri' => Config::get('uber_redirect_uri', ''),
    'store_id' => Config::get('uber_store_id', ''),
    'access_token' => Config::get('uber_access_token', ''),
    'refresh_token' => Config::get('uber_refresh_token', ''),
    'webhook_signing_key' => Config::get('uber_webhook_signing_key', ''),
    'external_order_injection_approved' => (bool)Config::get('uber_external_order_injection_approved', false),
    'direct_enabled' => (bool)Config::get('uber_direct_enabled', false),
];
