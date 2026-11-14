<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use App\Services\AuthService;

class CustomerController
{
    private CustomerRepository $repo;
    private OrderRepository $orderRepo;

    public function __construct()
    {
        $this->repo = new CustomerRepository();
        $this->orderRepo = new OrderRepository();
    }

    private function getAuthenticatedCustomerId(Request $request): int
    {
        $token = $request->getBearerToken();
        if ($token) {
            $payload = AuthService::verifyToken($token);
            if ($payload && isset($payload['customer_id'])) {
                return (int)$payload['customer_id'];
            }
        }
        return (int)Session::get('customer_id', 0);
    }

    public function profile(Request $request): Response
    {
        $customerId = $this->getAuthenticatedCustomerId($request);
        $customer = $this->repo->findById($customerId);

        if (!$customer) {
            return Response::error('Customer not found', 404);
        }

        return Response::success($customer);
    }

    public function updateProfile(Request $request): Response
    {
        $customerId = $this->getAuthenticatedCustomerId($request);
        $body = $request->getBody();

        $this->repo->updateProfile($customerId, $body);
        $updated = $this->repo->findById($customerId);

        return Response::success($updated, 'Profile updated successfully');
    }

    public function orders(Request $request): Response
    {
        $customerId = $this->getAuthenticatedCustomerId($request);
        $page = max(1, (int)$request->getQuery('page', 1));

        $result = $this->orderRepo->getPaginated(['customer_id' => $customerId], $page, 15);
        return Response::success($result['orders'], 'Customer orders', 200, $result['pagination']);
    }

    public function addresses(Request $request): Response
    {
        $customerId = $this->getAuthenticatedCustomerId($request);
        $addresses = $this->repo->getAddresses($customerId);
        return Response::success($addresses);
    }

    public function addAddress(Request $request): Response
    {
        $customerId = $this->getAuthenticatedCustomerId($request);
        $body = $request->getBody();

        if (empty($body['recipient_name']) || empty($body['phone']) || empty($body['address_line1']) || empty($body['city'])) {
            return Response::error('Required address fields are missing', 422);
        }

        $addressId = $this->repo->addAddress($customerId, $body);
        return Response::success(['id' => $addressId], 'Address added successfully', 201);
    }
}
