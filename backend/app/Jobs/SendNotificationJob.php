<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public string $type,
        public string $content,
        public string $channel = NotificationService::CHANNEL_LOG
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->send($this->userId, $this->type, $this->content, $this->channel);
    }
}
