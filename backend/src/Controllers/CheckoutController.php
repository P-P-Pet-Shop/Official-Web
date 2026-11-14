<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\CheckoutService;

class CheckoutController
{
    private CheckoutService $service;

    public function __construct()
    {
        $this->service = new CheckoutService();
    }

    public function process(Request $request): Response
    {
        $body = $request->getBody();
        $customerId = Session::get('customer_id');

        $result = $this->service->processCheckout($body, $customerId ? (int)$customerId : null);

        if (!$result['success']) {
            return Response::error($result['message'], 422, $result['errors'] ?? null);
        }

        return Response::success($result['order'], $result['message'], 201);
    }
}

