<?php

return [
    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_CALENDAR_REDIRECT_URI', env('APP_URL') . '/api/calendar/callback/google'),
    ],

    'microsoft_calendar' => [
        'client_id' => env('MICROSOFT_CALENDAR_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CALENDAR_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_CALENDAR_REDIRECT_URI', env('APP_URL') . '/api/calendar/callback/outlook'),
    ],
];
