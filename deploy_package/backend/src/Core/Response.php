<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private mixed $content = '';

    public function __construct(mixed $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $status;
        $this->headers = $headers;
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        return new self(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $status, $headers);
    }

    public static function success(mixed $data = null, string $message = 'Success', int $status = 200, array $meta = []): self
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }
        return self::json($payload, $status);
    }

    public static function error(string $message, int $status = 400, ?array $errors = null, ?string $code = null): self
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        if ($code !== null) {
            $payload['code'] = $code;
        }
        return self::json($payload, $status);
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        // Default security headers
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // CORS headers
        $allowedOrigin = Config::get('cors_origins', '*');
        header("Access-Control-Allow-Origin: {$allowedOrigin}");
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, X-Idempotency-Key");
        header("Access-Control-Allow-Credentials: true");

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        http_response_code($this->statusCode);
        echo $this->content;
        exit;
    }
}
