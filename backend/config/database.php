<?php
declare(strict_types=1);

use App\Core\Config;

return [
    'host' => Config::get('db_host', '127.0.0.1'),
    'port' => (int)Config::get('db_port', 3306),
    'name' => Config::get('db_name', 'pandp_petshop'),
    'user' => Config::get('db_user', 'root'),
    'pass' => Config::get('db_pass', ''),
    'charset' => Config::get('db_charset', 'utf8mb4'),
];

