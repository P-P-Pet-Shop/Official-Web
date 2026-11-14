<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Helpers\SecurityHelper;
use PDO;

class AuthService
{
    public static function createToken(array $payload, int $expirySeconds = 86400): string
    {
        $secret = Config::get('jwt_secret', 'pandp-default-secret-key-2026');
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $expirySeconds;

        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $secret, true);
        $base64Signature = self::base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    public static function verifyToken(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;
        $secret = Config::get('jwt_secret', 'pandp-default-secret-key-2026');

        $expectedSig = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $secret, true);
        $expectedBase64Sig = self::base64UrlEncode($expectedSig);

        if (!hash_equals($expectedBase64Sig, $base64Signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public static function authenticateCustomer(string $email, string $password): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM customers WHERE email = :email AND is_active = 1 LIMIT 1");
        $stmt->execute(['email' => strtolower(trim($email))]);
        $customer = $stmt->fetch();

        if (!$customer || empty($customer['password_hash'])) {
            return null;
        }

        if (!SecurityHelper::verifyPassword($password, $customer['password_hash'])) {
            return null;
        }

        unset($customer['password_hash']);
        return $customer;
    }

    public static function authenticateAdmin(string $username, string $password): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE (username = :u OR email = :u) AND is_active = 1 LIMIT 1");
        $stmt->execute(['u' => trim($username)]);
        $admin = $stmt->fetch();

        if (!$admin || empty($admin['password_hash'])) {
            return null;
        }

        if (!SecurityHelper::verifyPassword($password, $admin['password_hash'])) {
            return null;
        }

        // Update last login
        $upd = $db->prepare("UPDATE admin_users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id");
        $upd->execute(['id' => $admin['id']]);

        unset($admin['password_hash']);
        return $admin;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

