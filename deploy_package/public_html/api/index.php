<?php
declare(strict_types=1);

/**
 * P and P Pet Shop - Public API Gateway Proxy for cPanel
 * Bridges public_html/api/ to private backend directory.
 */

// Search paths for backend entrypoint
$candidatePaths = [
    // 1. Recommended cPanel secure location (outside public_html)
    dirname(__DIR__, 2) . '/backend/public/index.php',
    // 2. Parallel to public_html
    dirname(__DIR__) . '/../backend/public/index.php',
    // 3. Inside public_html/backend (fallback)
    dirname(__DIR__) . '/backend/public/index.php',
    // 4. Local repo development path
    __DIR__ . '/../../backend/public/index.php'
];

foreach ($candidatePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        exit;
    }
}

// If backend cannot be located, output clean JSON error
http_response_code(500);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Backend application entrypoint not found. Please verify the backend folder is uploaded.',
    'code' => 'BACKEND_NOT_FOUND'
]);
exit;
