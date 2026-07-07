<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $endpointId,
        public string $event,
        public array $payload
    ) {}

    public function handle(WebhookService $webhookService): void
    {
        $endpoint = WebhookEndpoint::find($this->endpointId);
        if ($endpoint && $endpoint->is_active) {
            $webhookService->send($endpoint, $this->event, $this->payload);
        }
    }
}
