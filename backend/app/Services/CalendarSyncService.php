<?php

namespace App\Services;

use App\Models\CalendarConnection;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalendarSyncService
{
    public function ensureFeedToken(User $user): string
    {
        if ($user->calendar_feed_token) {
            return $user->calendar_feed_token;
        }

        $user->calendar_feed_token = Str::random(48);
        $user->save();

        return $user->calendar_feed_token;
    }

    public function getFeedUrl(User $user): string
    {
        $token = $this->ensureFeedToken($user);
        return rtrim(config('app.url'), '/') . "/api/calendar/feed/{$token}.ics";
    }

    public function buildIcsForUser(User $user): string
    {
        $reservations = Reservation::where('user_id', $user->id)
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_RESERVED,
                Reservation::STATUS_CHECKIN,
            ])
            ->where('end_time', '>', now()->subDay())
            ->with('meetingRoom')
            ->orderBy('start_time')
            ->get();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MeetingRoom//CN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($reservations as $r) {
            $uid = "reservation-{$r->id}@meeting.sisuu.com";
            $room = $r->meetingRoom->name ?? '';
            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:' . $uid,
                'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
                'DTSTART:' . $r->start_time->utc()->format('Ymd\THis\Z'),
                'DTEND:' . $r->end_time->utc()->format('Ymd\THis\Z'),
                'SUMMARY:' . $this->escapeIcs($r->title),
                'LOCATION:' . $this->escapeIcs($room),
                'DESCRIPTION:' . $this->escapeIcs($r->description ?? ''),
                'STATUS:CONFIRMED',
                'END:VEVENT',
            ]);
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    public function getGoogleAuthUrl(User $user): ?string
    {
        $clientId = config('services.google_calendar.client_id');
        if (!$clientId) {
            return null;
        }

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => config('services.google_calendar.redirect'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => base64_encode(json_encode(['user_id' => $user->id])),
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    public function getOutlookAuthUrl(User $user): ?string
    {
        $clientId = config('services.microsoft_calendar.client_id');
        if (!$clientId) {
            return null;
        }

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => config('services.microsoft_calendar.redirect'),
            'response_type' => 'code',
            'scope' => 'offline_access Calendars.ReadWrite User.Read',
            'state' => base64_encode(json_encode(['user_id' => $user->id])),
        ]);

        return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . $params;
    }

    public function handleGoogleCallback(string $code, User $user): CalendarConnection
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'redirect_uri' => config('services.google_calendar.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Google OAuth 失败');
        }

        $data = $response->json();

        return CalendarConnection::updateOrCreate(
            ['user_id' => $user->id, 'provider' => 'google'],
            [
                'tenant_id' => $user->tenant_id,
                'access_token' => Crypt::encryptString($data['access_token']),
                'refresh_token' => isset($data['refresh_token']) ? Crypt::encryptString($data['refresh_token']) : null,
                'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                'sync_enabled' => true,
            ]
        );
    }

    public function handleOutlookCallback(string $code, User $user): CalendarConnection
    {
        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'code' => $code,
            'client_id' => config('services.microsoft_calendar.client_id'),
            'client_secret' => config('services.microsoft_calendar.client_secret'),
            'redirect_uri' => config('services.microsoft_calendar.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Outlook OAuth 失败');
        }

        $data = $response->json();

        return CalendarConnection::updateOrCreate(
            ['user_id' => $user->id, 'provider' => 'outlook'],
            [
                'tenant_id' => $user->tenant_id,
                'access_token' => Crypt::encryptString($data['access_token']),
                'refresh_token' => isset($data['refresh_token']) ? Crypt::encryptString($data['refresh_token']) : null,
                'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                'sync_enabled' => true,
            ]
        );
    }

    public function syncReservation(Reservation $reservation): void
    {
        $user = $reservation->user;
        if (!$user) {
            return;
        }

        CalendarConnection::where('user_id', $user->id)
            ->where('sync_enabled', true)
            ->each(function (CalendarConnection $conn) use ($reservation) {
                try {
                    if ($conn->provider === 'google') {
                        $this->syncToGoogle($conn, $reservation);
                    } elseif ($conn->provider === 'outlook') {
                        $this->syncToOutlook($conn, $reservation);
                    }
                } catch (\Throwable $e) {
                    Log::warning("[CalendarSync] {$conn->provider} 同步失败: {$e->getMessage()}");
                }
            });
    }

    public function deleteEvent(Reservation $reservation): void
    {
        $user = $reservation->user;
        if (!$user) {
            return;
        }

        if ($reservation->google_event_id) {
            $conn = CalendarConnection::where('user_id', $user->id)->where('provider', 'google')->first();
            if ($conn) {
                $this->deleteGoogleEvent($conn, $reservation->google_event_id);
            }
        }

        if ($reservation->outlook_event_id) {
            $conn = CalendarConnection::where('user_id', $user->id)->where('provider', 'outlook')->first();
            if ($conn) {
                $this->deleteOutlookEvent($conn, $reservation->outlook_event_id);
            }
        }
    }

    protected function syncToGoogle(CalendarConnection $conn, Reservation $reservation): void
    {
        $token = $this->getAccessToken($conn);
        if (!$token) {
            return;
        }

        $reservation->load('meetingRoom');
        $calendarId = $conn->calendar_id ?: 'primary';
        $event = [
            'summary' => $reservation->title,
            'location' => $reservation->meetingRoom->name ?? '',
            'description' => $reservation->description,
            'start' => ['dateTime' => $reservation->start_time->toIso8601String(), 'timeZone' => config('app.timezone')],
            'end' => ['dateTime' => $reservation->end_time->toIso8601String(), 'timeZone' => config('app.timezone')],
        ];

        if ($reservation->google_event_id) {
            Http::withToken($token)->put(
                "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$reservation->google_event_id}",
                $event
            );
        } else {
            $response = Http::withToken($token)->post(
                "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events",
                $event
            );
            if ($response->successful()) {
                $reservation->update(['google_event_id' => $response->json('id')]);
            }
        }
    }

    protected function syncToOutlook(CalendarConnection $conn, Reservation $reservation): void
    {
        $token = $this->getAccessToken($conn);
        if (!$token) {
            return;
        }

        $reservation->load('meetingRoom');
        $event = [
            'subject' => $reservation->title,
            'location' => ['displayName' => $reservation->meetingRoom->name ?? ''],
            'body' => ['contentType' => 'text', 'content' => $reservation->description ?? ''],
            'start' => ['dateTime' => $reservation->start_time->format('Y-m-d\TH:i:s'), 'timeZone' => config('app.timezone')],
            'end' => ['dateTime' => $reservation->end_time->format('Y-m-d\TH:i:s'), 'timeZone' => config('app.timezone')],
        ];

        if ($reservation->outlook_event_id) {
            Http::withToken($token)->patch(
                "https://graph.microsoft.com/v1.0/me/events/{$reservation->outlook_event_id}",
                $event
            );
        } else {
            $response = Http::withToken($token)->post('https://graph.microsoft.com/v1.0/me/events', $event);
            if ($response->successful()) {
                $reservation->update(['outlook_event_id' => $response->json('id')]);
            }
        }
    }

    protected function deleteGoogleEvent(CalendarConnection $conn, string $eventId): void
    {
        $token = $this->getAccessToken($conn);
        if ($token) {
            Http::withToken($token)->delete(
                'https://www.googleapis.com/calendar/v3/calendars/' . ($conn->calendar_id ?: 'primary') . "/events/{$eventId}"
            );
        }
    }

    protected function deleteOutlookEvent(CalendarConnection $conn, string $eventId): void
    {
        $token = $this->getAccessToken($conn);
        if ($token) {
            Http::withToken($token)->delete("https://graph.microsoft.com/v1.0/me/events/{$eventId}");
        }
    }

    protected function getAccessToken(CalendarConnection $conn): ?string
    {
        if (!$conn->access_token) {
            return null;
        }

        try {
            $token = Crypt::decryptString($conn->access_token);
        } catch (\Throwable $e) {
            return $conn->access_token;
        }

        if ($conn->expires_at && $conn->expires_at->isPast() && $conn->refresh_token) {
            return $this->refreshToken($conn);
        }

        return $token;
    }

    protected function refreshToken(CalendarConnection $conn): ?string
    {
        try {
            $refresh = Crypt::decryptString($conn->refresh_token);
        } catch (\Throwable $e) {
            $refresh = $conn->refresh_token;
        }

        if ($conn->provider === 'google') {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ]);
        } else {
            $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => config('services.microsoft_calendar.client_id'),
                'client_secret' => config('services.microsoft_calendar.client_secret'),
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ]);
        }

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        $conn->update([
            'access_token' => Crypt::encryptString($data['access_token']),
            'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
        ]);

        return $data['access_token'];
    }

    protected function escapeIcs(string $value): string
    {
        return str_replace(["\r", "\n", ',', ';'], ['', '\\n', '\\,', '\\;'], $value);
    }
}
