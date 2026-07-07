<?php

namespace App\Http\Controllers;

use App\Models\TenantApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantApiTokenController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('admin');

        $tokens = TenantApiToken::orderByDesc('id')
            ->get(['id', 'name', 'token_prefix', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        return response()->json($tokens);
    }

    public function store(Request $request)
    {
        $this->authorize('admin');

        $request->validate([
            'name' => 'required|string|max:100',
            'abilities' => 'nullable|array',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $plain = Str::random(40);
        $prefix = Str::random(8);

        $token = TenantApiToken::create([
            'name' => $request->name,
            'token_prefix' => $prefix,
            'token_hash' => hash('sha256', $plain),
            'abilities' => $request->abilities ?? ['reservations:read', 'reservations:write', 'rooms:read'],
            'expires_at' => $request->expires_at,
        ]);

        return response()->json([
            'token' => $token,
            'plain_text_token' => "{$prefix}.{$plain}",
            'message' => '请妥善保存 Token，仅显示一次',
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorize('admin');
        TenantApiToken::findOrFail($id)->delete();
        return response()->json(['message' => '已删除']);
    }
}
