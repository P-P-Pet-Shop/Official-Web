<?php
declare(strict_types=1);

namespace Uber;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use PDO;

class OAuthManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        $clientId = Config::get('uber_client_id', '');
        $scopes = implode(' ', [
            'eats.store',
            'eats.store.orders.read',
            'eats.report',
            'eats.deliveries',
        ]);

        $params = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => $scopes,
            'state' => $state,
        ]);

        return "https://login.uber.com/oauth/v2/authorize?{$params}";
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $clientId = Config::get('uber_client_id', '');
        $clientSecret = Config::get('uber_client_secret', '');

        $postData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ];

        return $this->requestToken($postData);
    }

    public function refreshToken(): bool
    {
        $refreshToken = $this->getStoredSetting('uber_refresh_token') ?: Config::get('uber_refresh_token', '');
        if (empty($refreshToken)) {
            Logger::uber('WARNING', "Cannot refresh Uber token: No refresh token is stored.");
            return false;
        }

        $clientId = Config::get('uber_client_id', '');
        $clientSecret = Config::get('uber_client_secret', '');

        $postData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        $res = $this->requestToken($postData);
        return $res['success'];
    }

    private function requestToken(array $postData): array
    {
        $ch = curl_init('https://login.uber.com/oauth/v2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string)$response, true);

        if ($httpCode === 200 && !empty($data['access_token'])) {
            $this->storeTokenData($data);
            Logger::uber('INFO', "Successfully acquired new Uber OAuth access token.");
            return ['success' => true, 'data' => $data];
        }

        $err = $data['error_description'] ?? $data['error'] ?? 'Token request failed';
        Logger::uber('ERROR', "Uber OAuth token request failed [{$httpCode}]: {$err}");
        return ['success' => false, 'error' => $err];
    }

    public function getValidAccessToken(): ?string
    {
        // 1. Check database setting first
        $token = $this->getStoredSetting('uber_access_token');
        $expiresAt = (int)($this->getStoredSetting('uber_token_expires_at') ?? 0);

        if ($token && $expiresAt > (time() + 60)) {
            return $token;
        }

        // 2. Fall back to config/env token
        $envToken = Config::get('uber_access_token', '');
        if (!empty($envToken)) {
            return $envToken;
        }

        // 3. Try refreshing if we have refresh token
        if ($this->refreshToken()) {
            return $this->getStoredSetting('uber_access_token');
        }

        return null;
    }

    public function isConnected(): bool
    {
        return !empty($this->getValidAccessToken());
    }

    public function storeTokenData(array $tokenData): void
    {
        $accessToken = $tokenData['access_token'];
        $expiresIn = (int)($tokenData['expires_in'] ?? 2592000); // 30 days default
        $expiresAt = time() + $expiresIn;

        $this->saveStoredSetting('uber_access_token', $accessToken);
        $this->saveStoredSetting('uber_token_expires_at', (string)$expiresAt);

        if (!empty($tokenData['refresh_token'])) {
            $this->saveStoredSetting('uber_refresh_token', $tokenData['refresh_token']);
        }
    }

    public function disconnect(): void
    {
        $this->saveStoredSetting('uber_access_token', '');
        $this->saveStoredSetting('uber_refresh_token', '');
        $this->saveStoredSetting('uber_token_expires_at', '0');
        Logger::uber('INFO', "Uber integration disconnected by admin.");
    }

    private function getStoredSetting(string $key): ?string
    {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM integration_settings WHERE setting_key = :k LIMIT 1");
            $stmt->execute(['k' => $key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (string)$val : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function saveStoredSetting(string $key, string $value): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO integration_settings (setting_group, setting_key, setting_value)
                VALUES ('UBER', :k, :v)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = UTC_TIMESTAMP()
            ");
            $stmt->execute(['k' => $key, 'v' => $value]);
        } catch (\Throwable $e) {
            Logger::error("Failed to save integration setting: " . $e->getMessage());
        }
    }
}
