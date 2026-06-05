<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $query = Tenant::query();

        if ($request->has('keyword')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->keyword . '%')
                  ->orWhere('domain', 'like', '%' . $request->keyword . '%')
                  ->orWhere('contact_name', 'like', '%' . $request->keyword . '%');
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tenants = $query->paginate(20);

        return response()->json($tenants);
    }

    public function show($id)
    {
        $tenant = Tenant::with(['users', 'meetingRooms'])->findOrFail($id);

        $stats = [
            'users_count' => $tenant->users()->count(),
            'rooms_count' => $tenant->meetingRooms()->count(),
            'active_bookings' => $tenant->meetingRooms()->withCount('reservations')->get()->sum('reservations_count'),
        ];

        return response()->json([
            'tenant' => $tenant,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'domain' => 'nullable|string|max:100|unique:tenants,domain',
            'contact_name' => 'nullable|string|max:50',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:100',
            'subscription_until' => 'nullable|date',
        ]);

        $tenant = Tenant::create([
            'name' => $request->name,
            'domain' => $request->domain,
            'contact_name' => $request->contact_name,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'status' => 1,
            'subscription_until' => $request->subscription_until,
        ]);

        return response()->json($tenant, 201);
    }

    public function update(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string|max:100',
            'domain' => 'nullable|string|max:100|unique:tenants,domain,' . $id,
            'contact_name' => 'nullable|string|max:50',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:100',
            'subscription_until' => 'nullable|date',
            'status' => 'nullable|in:0,1',
        ]);

        $tenant->update($request->only([
            'name', 'domain', 'contact_name', 'contact_phone',
            'contact_email', 'subscription_until', 'status'
        ]));

        return response()->json($tenant);
    }

    public function renew(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);

        $request->validate([
            'subscription_until' => 'required|date',
        ]);

        $tenant->update([
            'subscription_until' => $request->subscription_until,
            'status' => 1, // 续费后自动启用
        ]);

        return response()->json([
            'message' => '续费成功',
            'tenant' => $tenant,
        ]);
    }

    public function destroy($id)
    {
        $tenant = Tenant::findOrFail($id);

        if ($tenant->users()->exists()) {
            return response()->json(['message' => '该租户下存在用户，无法删除'], 400);
        }

        if ($tenant->meetingRooms()->exists()) {
            return response()->json(['message' => '该租户下存在会议室，无法删除'], 400);
        }

        $tenant->delete();

        return response()->json(['message' => '删除成功']);
    }

    public function createAdmin(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'name' => 'required|string|max:50',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'position' => '系统管理员',
            'is_admin' => 1,
            'is_super_admin' => 0,
            'status' => 1,
            'credit_score' => 100,
        ]);

        return response()->json([
            'message' => '管理员创建成功',
            'admin' => $admin,
        ], 201);
    }

    public function stats($id)
    {
        $tenant = Tenant::findOrFail($id);

        $stats = [
            'users_count' => $tenant->users()->count(),
            'rooms_count' => $tenant->meetingRooms()->count(),
            'today_bookings' => $tenant->reservations()
                ->whereDate('start_time', today())
                ->count(),
            'week_bookings' => $tenant->reservations()
                ->whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'month_bookings' => $tenant->reservations()
                ->whereMonth('start_time', now()->month)
                ->count(),
            'active_reservations' => $tenant->reservations()
                ->whereIn('status', [1, 2])
                ->count(),
        ];

        return response()->json($stats);
    }
}
