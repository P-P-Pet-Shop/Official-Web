<?php
declare(strict_types=1);

namespace Uber;

use App\Core\Config;
use App\Core\Logger;
use RuntimeException;

class UberClient
{
    private string $baseUrl;
    private OAuthManager $oauth;

    public function __construct(?OAuthManager $oauth = null)
    {
        $env = Config::get('uber_environment', 'sandbox');
        $this->baseUrl = ($env === 'production') 
            ? 'https://api.uber.com' 
            : 'https://test-api.uber.com';
        
        $this->oauth = $oauth ?? new OAuthManager($this);
    }

    public function request(string $method, string $endpoint, array $data = [], array $headers = [], int $retryCount = 0): array
    {
        $token = $this->oauth->getValidAccessToken();
        
        $url = str_starts_with($endpoint, 'http') ? $endpoint : $this->baseUrl . $endpoint;
        $ch = curl_init();

        $defaultHeaders = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: PandPPetShop-UberIntegration/1.0',
        ];

        if ($token) {
            $defaultHeaders[] = "Authorization: Bearer {$token}";
        }

        $allHeaders = array_merge($defaultHeaders, $headers);

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if (!empty($data) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $curlOptions);

        $rawResponse = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            Logger::uber('ERROR', "cURL failure contacting Uber API [{$method} {$url}]: {$curlError}");
            throw new RuntimeException("cURL network error contacting Uber API: {$curlError}");
        }

        $decoded = json_decode((string)$rawResponse, true);
        $responseData = is_array($decoded) ? $decoded : ['raw' => $rawResponse];

        // Handle Token Expiration (401 Unauthorized)
        if ($httpCode === 401 && $retryCount === 0) {
            Logger::uber('WARNING', "Uber access token expired or invalid (401). Attempting refresh...");
            if ($this->oauth->refreshToken()) {
                return $this->request($method, $endpoint, $data, $headers, $retryCount + 1);
            }
        }

        // Handle Rate Limiting (429 Too Many Requests) with Exponential Backoff
        if ($httpCode === 429 && $retryCount < 2) {
            $backoffSeconds = ($retryCount + 1) * 2;
            Logger::uber('WARNING', "Uber rate limit hit (429). Backing off for {$backoffSeconds}s (retry " . ($retryCount + 1) . ")");
            sleep($backoffSeconds);
            return $this->request($method, $endpoint, $data, $headers, $retryCount + 1);
        }

        if ($httpCode >= 400) {
            $errMsg = $responseData['message'] ?? $responseData['error'] ?? "HTTP {$httpCode}";
            Logger::uber('ERROR', "Uber API returned error [{$httpCode}] for {$method} {$url}: {$errMsg}", [
                'response' => $responseData
            ]);
            return [
                'success' => false,
                'status_code' => $httpCode,
                'error' => $errMsg,
                'data' => $responseData,
            ];
        }

        return [
            'success' => true,
            'status_code' => $httpCode,
            'data' => $responseData,
        ];
    }

    public function get(string $endpoint, array $headers = []): array
    {
        return $this->request('GET', $endpoint, [], $headers);
    }

    public function post(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $endpoint, $data, $headers);
    }

    public function put(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request('PUT', $endpoint, $data, $headers);
    }

    public function patch(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request('PATCH', $endpoint, $data, $headers);
    }

    public function delete(string $endpoint, array $headers = []): array
    {
        return $this->request('DELETE', $endpoint, [], $headers);
    }
}
