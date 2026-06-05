<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->isSuperAdmin()) {
            return response()->json(['message' => '无权限访问'], 403);
        }

        return $next($request);
    }
}
