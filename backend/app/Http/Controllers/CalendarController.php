<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CalendarSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CalendarController extends Controller
{
    public function status(Request $request, CalendarSyncService $calendarSync)
    {
        $user = $request->user();
        $connections = $user->calendarConnections()->get(['provider', 'account_email', 'sync_enabled', 'created_at']);

        return response()->json([
            'feed_url' => $calendarSync->getFeedUrl($user),
            'connections' => $connections,
            'google_auth_url' => $calendarSync->getGoogleAuthUrl($user),
            'outlook_auth_url' => $calendarSync->getOutlookAuthUrl($user),
            'google_configured' => (bool) config('services.google_calendar.client_id'),
            'outlook_configured' => (bool) config('services.microsoft_calendar.client_id'),
        ]);
    }

    public function feed(string $token, CalendarSyncService $calendarSync)
    {
        $user = User::withoutGlobalScopes()->where('calendar_feed_token', $token)->first();
        if (!$user) {
            abort(404);
        }

        $ics = $calendarSync->buildIcsForUser($user);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="meetings.ics"',
        ]);
    }

    public function googleCallback(Request $request, CalendarSyncService $calendarSync)
    {
        return $this->handleOAuthCallback($request, $calendarSync, 'google');
    }

    public function outlookCallback(Request $request, CalendarSyncService $calendarSync)
    {
        return $this->handleOAuthCallback($request, $calendarSync, 'outlook');
    }

    protected function handleOAuthCallback(Request $request, CalendarSyncService $calendarSync, string $provider)
    {
        $state = json_decode(base64_decode($request->state ?? ''), true);
        $user = User::withoutGlobalScopes()->find($state['user_id'] ?? 0);

        if (!$user || !$request->code) {
            return redirect('/profile?calendar=error');
        }

        try {
            if ($provider === 'google') {
                $calendarSync->handleGoogleCallback($request->code, $user);
            } else {
                $calendarSync->handleOutlookCallback($request->code, $user);
            }
            return redirect('/profile?calendar=' . $provider . '_ok');
        } catch (\Throwable $e) {
            Log::error("[Calendar] {$provider} callback: " . $e->getMessage());
            return redirect('/profile?calendar=error');
        }
    }

    public function disconnect(Request $request, string $provider)
    {
        $request->user()->calendarConnections()->where('provider', $provider)->delete();
        return response()->json(['message' => '已断开连接']);
    }

    public function regenerateFeed(Request $request)
    {
        $user = $request->user();
        $user->calendar_feed_token = \Illuminate\Support\Str::random(48);
        $user->save();

        return response()->json(['feed_url' => app(CalendarSyncService::class)->getFeedUrl($user)]);
    }
}
