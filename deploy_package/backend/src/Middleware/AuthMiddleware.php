<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

class AuthMiddleware
{
    public function handle(Request $request): ?Response
    {
        $token = $request->getBearerToken();
        $customerId = null;

        if ($token) {
            $payload = AuthService::verifyToken($token);
            if ($payload && isset($payload['customer_id'])) {
                $customerId = (int)$payload['customer_id'];
            }
        } elseif (Session::has('customer_id')) {
            $customerId = (int)Session::get('customer_id');
        }

        if (!$customerId) {
            return Response::error('Unauthorized access. Please log in.', 401, null, 'AUTH_REQUIRED');
        }

        // Store customer_id in request attributes
        $request->setRouteParams(array_merge(['auth_customer_id' => $customerId], []));
        return null;
    }
}
