<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class CustomerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, first_name, last_name, email, phone, is_active, created_at FROM customers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO customers (first_name, last_name, email, phone, password_hash)
            VALUES (:first_name, :last_name, :email, :phone, :password_hash)
        ");
        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'],
            'password_hash' => $data['password_hash'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateProfile(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['first_name', 'last_name', 'phone'] as $key) {
            if (isset($data[$key])) {
                $fields[] = "`{$key}` = :{$key}";
                $params[$key] = $data[$key];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function getAddresses(int $customerId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM customer_addresses WHERE customer_id = :id ORDER BY is_default DESC, id DESC");
        $stmt->execute(['id' => $customerId]);
        return $stmt->fetchAll();
    }

    public function addAddress(int $customerId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO customer_addresses (
                customer_id, address_title, recipient_name, phone, address_line1,
                address_line2, city, district, postal_code, delivery_instructions, is_default
            ) VALUES (
                :customer_id, :address_title, :recipient_name, :phone, :address_line1,
                :address_line2, :city, :district, :postal_code, :delivery_instructions, :is_default
            )
        ");
        $stmt->execute([
            'customer_id' => $customerId,
            'address_title' => $data['address_title'] ?? 'Home',
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'],
            'district' => $data['district'] ?? 'Colombo',
            'postal_code' => $data['postal_code'] ?? null,
            'delivery_instructions' => $data['delivery_instructions'] ?? null,
            'is_default' => !empty($data['is_default']) ? 1 : 0,
        ]);
        return (int)$this->db->lastInsertId();
    }
}

