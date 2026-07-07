<?php

namespace App\Http\Middleware;

use App\Models\TenantApiToken;
use Closure;
use Illuminate\Http\Request;

class AuthenticateTenantApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->bearerToken();
        if (!$header || !str_contains($header, '.')) {
            return response()->json(['message' => '未授权'], 401);
        }

        [$prefix, $plain] = explode('.', $header, 2);
        $token = TenantApiToken::where('token_prefix', $prefix)->first();

        if (!$token || !hash_equals($token->token_hash, hash('sha256', $plain))) {
            return response()->json(['message' => 'API Token 无效'], 401);
        }

        if ($token->isExpired()) {
            return response()->json(['message' => 'API Token 已过期'], 401);
        }

        $token->update(['last_used_at' => now()]);
        $request->attributes->set('tenant_api_token', $token);
        $request->attributes->set('tenant_id', $token->tenant_id);

        return $next($request);
    }
}
