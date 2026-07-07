<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\CalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncReservationCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $reservationId,
        public string $action = 'upsert'
    ) {}

    public function handle(CalendarSyncService $calendarSyncService): void
    {
        $reservation = Reservation::with(['meetingRoom', 'user'])->find($this->reservationId);
        if (!$reservation) {
            return;
        }

        match ($this->action) {
            'delete' => $calendarSyncService->deleteEvent($reservation),
            default => $calendarSyncService->syncReservation($reservation),
        };
    }
}
