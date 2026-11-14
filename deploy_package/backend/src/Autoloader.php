<?php
declare(strict_types=1);

/**
 * P and P Pet Shop - Universal Autoloader (PSR-4 Compliant)
 * Works seamlessly with or without Composer.
 */

spl_autoload_register(function (string $class): void {
    // Check if Composer autoloader exists first
    $composerAutoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($composerAutoload) && !defined('COMPOSER_AUTOLOAD_LOADED')) {
        define('COMPOSER_AUTOLOAD_LOADED', true);
        require_once $composerAutoload;
    }

    $prefixes = [
        'App\\' => __DIR__ . '/',
        'Uber\\' => __DIR__ . '/../integrations/Uber/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
