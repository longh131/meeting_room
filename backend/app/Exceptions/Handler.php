<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return redirect()->guest(route('login'));
    }

    public function register()
    {
        $this->reportable(function (Throwable $e) {
        });
    }
}
