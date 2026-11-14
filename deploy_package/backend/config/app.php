<?php
declare(strict_types=1);

use App\Core\Config;

return [
    'env' => Config::get('app_env', 'production'),
    'debug' => Config::isDebug(),
    'url' => Config::get('app_url', 'https://pandppetshop.lk'),
    'api_prefix' => Config::get('api_prefix', '/api/v1'),
    'currency' => Config::get('currency', 'LKR'),
    'currency_symbol' => Config::get('currency_symbol', 'Rs.'),
    'timezone' => Config::get('timezone', 'Asia/Colombo'),
    'session_lifetime' => (int)Config::get('session_lifetime', 7200),
];
