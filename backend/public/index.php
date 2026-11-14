<?php
declare(strict_types=1);

/**
 * P and P Pet Shop - Public API Gateway Entry Point
 * PHP 8.3 REST API
 */

require_once __DIR__ . '/../src/Autoloader.php';

use App\Core\App;

// Instantiate and run the application
$app = new App();
$app->run();

