<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\SecurityHelper;
use App\Repositories\CustomerRepository;
use App\Services\AuthService;

class AuthController
{
    private CustomerRepository $customerRepo;

    public function __construct()
    {
        $this->customerRepo = new CustomerRepository();
    }

    public function register(Request $request): Response
    {
        $body = $request->getBody();
        $firstName = trim($body['first_name'] ?? '');
        $lastName = trim($body['last_name'] ?? '');
        $email = strtolower(trim($body['email'] ?? ''));
        $phone = trim($body['phone'] ?? '');
        $password = $body['password'] ?? '';

        $errors = [];
        if (strlen($firstName) < 2) $errors['first_name'] = 'First name is required.';
        if (strlen($lastName) < 1) $errors['last_name'] = 'Last name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
        if (strlen($phone) < 9) $errors['phone'] = 'Valid phone number is required.';
        if (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';

        if (!empty($errors)) {
            return Response::error('Validation failed', 422, $errors);
        }

        if ($this->customerRepo->findByEmail($email)) {
            return Response::error('An account with this email address already exists.', 409, null, 'EMAIL_EXISTS');
        }

        $passwordHash = SecurityHelper::hashPassword($password);
        $customerId = $this->customerRepo->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $passwordHash,
        ]);

        $token = AuthService::createToken(['customer_id' => $customerId, 'email' => $email]);
        Session::set('customer_id', $customerId);

        return Response::success([
            'token' => $token,
            'customer' => [
                'id' => $customerId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
            ]
        ], 'Account registered successfully', 201);
    }

    public function customerLogin(Request $request): Response
    {
        $body = $request->getBody();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        if (empty($email) || empty($password)) {
            return Response::error('Email and password are required', 400);
        }

        $customer = AuthService::authenticateCustomer($email, $password);
        if (!$customer) {
            return Response::error('Invalid email or password', 401, null, 'INVALID_CREDENTIALS');
        }

        $token = AuthService::createToken(['customer_id' => $customer['id'], 'email' => $customer['email']]);
        Session::set('customer_id', $customer['id']);

        return Response::success([
            'token' => $token,
            'customer' => $customer,
        ], 'Logged in successfully');
    }

    public function logout(Request $request): Response
    {
        Session::remove('customer_id');
        return Response::success(null, 'Logged out successfully');
    }

    public function adminLogin(Request $request): Response
    {
        $body = $request->getBody();
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';

        if (empty($username) || empty($password)) {
            return Response::error('Username and password are required', 400);
        }

        $admin = AuthService::authenticateAdmin($username, $password);
        if (!$admin) {
            return Response::error('Invalid administrator credentials', 401, null, 'ADMIN_INVALID_CREDENTIALS');
        }

        $token = AuthService::createToken([
            'admin_id' => $admin['id'],
            'username' => $admin['username'],
            'role' => $admin['role'],
        ], 28800); // 8 hours

        Session::set('admin_id', $admin['id']);
        Session::set('admin_role', $admin['role']);

        return Response::success([
            'token' => $token,
            'admin' => $admin,
        ], 'Admin logged in successfully');
    }

    public function adminLogout(Request $request): Response
    {
        Session::remove('admin_id');
        Session::remove('admin_role');
        return Response::success(null, 'Admin logged out successfully');
    }
}
