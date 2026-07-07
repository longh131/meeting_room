<?php

namespace App\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function dispatch(?int $tenantId, string $event, array $payload): void
    {
        if (!$tenantId) {
            return;
        }

        $endpoints = WebhookEndpoint::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->filter(fn ($ep) => in_array($event, $ep->events ?? [], true));

        foreach ($endpoints as $endpoint) {
            if (config('queue.default') !== 'sync') {
                DispatchWebhookJob::dispatch($endpoint->id, $event, $payload);
            } else {
                $this->send($endpoint, $event, $payload);
            }
        }
    }

    public function send(WebhookEndpoint $endpoint, string $event, array $payload): bool
    {
        $body = json_encode([
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ], JSON_UNESCAPED_UNICODE);

        $headers = ['Content-Type' => 'application/json'];
        if ($endpoint->secret) {
            $headers['X-Webhook-Signature'] = hash_hmac('sha256', $body, $endpoint->secret);
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->post($endpoint->url, [
                'headers' => $headers,
                'body' => $body,
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable $e) {
            Log::warning("[Webhook] 投递失败 endpoint={$endpoint->id}: {$e->getMessage()}");
            return false;
        }
    }
}
