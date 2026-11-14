<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CartService;

class CartController
{
    private CartService $service;

    public function __construct()
    {
        $this->service = new CartService();
    }

    public function validateCart(Request $request): Response
    {
        $body = $request->getBody();
        $items = $body['items'] ?? [];
        $district = $body['district'] ?? 'Colombo';

        $result = $this->service->validateAndCalculate($items, $district);

        return Response::success($result, 'Cart calculated successfully');
    }
}

