<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

class AdminMiddleware
{
    public function handle(Request $request): ?Response
    {
        $token = $request->getBearerToken();
        $adminId = null;
        $adminRole = null;

        if ($token) {
            $payload = AuthService::verifyToken($token);
            if ($payload && isset($payload['admin_id'])) {
                $adminId = (int)$payload['admin_id'];
                $adminRole = $payload['role'] ?? 'STAFF';
            }
        } elseif (Session::has('admin_id')) {
            $adminId = (int)Session::get('admin_id');
            $adminRole = Session::get('admin_role', 'STAFF');
        }

        if (!$adminId) {
            return Response::error('Administrator authentication required.', 401, null, 'ADMIN_AUTH_REQUIRED');
        }

        $request->setRouteParams(array_merge([
            'auth_admin_id' => $adminId,
            'auth_admin_role' => $adminRole
        ], []));

        return null;
    }
}
