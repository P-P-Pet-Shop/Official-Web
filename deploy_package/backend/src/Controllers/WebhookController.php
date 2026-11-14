<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use Uber\WebhookService;

class WebhookController
{
    private WebhookService $service;

    public function __construct()
    {
        $this->service = new WebhookService();
    }

    public function handleUberWebhook(Request $request): Response
    {
        $rawPayload = $request->getRawBody();
        $signature = $request->getHeader('x-uber-signature');

        // Verify HMAC signature
        if (!$this->service->verifySignature($rawPayload, $signature)) {
            return Response::error('Invalid webhook signature verification', 401, null, 'INVALID_WEBHOOK_SIGNATURE');
        }

        $payload = $request->getBody();
        $result = $this->service->handleEvent($payload);

        return Response::success($result, 'Webhook received', 200);
    }
}
