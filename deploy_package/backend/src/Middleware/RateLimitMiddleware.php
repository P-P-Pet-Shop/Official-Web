<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

class RateLimitMiddleware
{
    private static string $cacheDir = __DIR__ . '/../../storage/cache/rate_limits';

    public function handle(Request $request): ?Response
    {
        $limit = (int)Config::get('rate_limit_per_minute', 60);
        $ip = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $request->getClientIp());
        $minute = date('YmdHi');

        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }

        $file = self::$cacheDir . '/' . $ip . '_' . $minute . '.count';
        $count = 0;

        if (file_exists($file)) {
            $count = (int)file_get_contents($file);
        }

        $count++;
        @file_put_contents($file, (string)$count, LOCK_EX);

        if ($count > $limit) {
            return Response::error('Too many requests. Please slow down.', 429, [
                'limit' => $limit,
                'retry_after' => 60
            ], 'RATE_LIMIT_EXCEEDED');
        }

        return null;
    }
}
