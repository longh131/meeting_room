<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant;

class CheckTenantStatus
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && !$user->is_super_admin) {
            $tenant = Tenant::find($user->tenant_id);

            if (!$tenant || !$tenant->isActive()) {
                Auth::logout();
                return response()->json([
                    'message' => '您的租户账户已过期或被停用，请联系管理员',
                    'error' => 'tenant_inactive'
                ], 403);
            }
        }

        return $next($request);
    }
}