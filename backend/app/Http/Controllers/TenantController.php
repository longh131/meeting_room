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

        // 获取租户管理员信息
        $admin = $tenant->users()->where('is_admin', 1)->first();

        return response()->json([
            'tenant' => $tenant,
            'stats' => $stats,
            'admin' => $admin ? [
                'email' => $admin->email,
                'name' => $admin->name,
                'phone' => $admin->phone,
            ] : null,
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
            
            // 管理员账号（可选）
            'admin_email' => 'nullable|email|max:100',
            'admin_password' => 'nullable|string|min:6',
            'admin_name' => 'nullable|string|max:50',
            'admin_phone' => 'nullable|string|max:20',
            
            // 钉钉配置
            'dingtalk_enabled' => 'nullable|boolean',
            'dingtalk_app_key' => 'nullable|string|max:100',
            'dingtalk_app_secret' => 'nullable|string|max:100',
            'dingtalk_corp_id' => 'nullable|string|max:100',
            'dingtalk_agent_id' => 'nullable|string|max:50',
            'dingtalk_process_code' => 'nullable|string|max:100',
            
            // 飞书配置
            'feishu_enabled' => 'nullable|boolean',
            'feishu_app_id' => 'nullable|string|max:100',
            'feishu_app_secret' => 'nullable|string|max:100',
            'feishu_verification_token' => 'nullable|string|max:255',
            'feishu_app_type' => 'nullable|in:self,store',
            'feishu_approval_code' => 'nullable|string|max:100',
            
            // 企业微信配置
            'wework_enabled' => 'nullable|boolean',
            'wework_corp_id' => 'nullable|string|max:100',
            'wework_secret' => 'nullable|string|max:100',
            'wework_agent_id' => 'nullable|string|max:50',
            'wework_token' => 'nullable|string|max:100',
            'wework_encoding_aes_key' => 'nullable|string|max:100',
            'wework_approval_code' => 'nullable|string|max:100',
            
            // IM通知渠道
            'im_notification_channel' => 'nullable|in:log,dingtalk,feishu,wework',
        ]);

        $tenant = Tenant::create([
            'name' => $request->name,
            'domain' => $request->domain,
            'contact_name' => $request->contact_name,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'status' => 1,
            'subscription_until' => $request->subscription_until,
            // 钉钉配置
            'dingtalk_enabled' => $request->dingtalk_enabled ?? false,
            'dingtalk_app_key' => $request->dingtalk_app_key,
            'dingtalk_app_secret' => $request->dingtalk_app_secret,
            'dingtalk_corp_id' => $request->dingtalk_corp_id,
            'dingtalk_agent_id' => $request->dingtalk_agent_id,
            'dingtalk_process_code' => $request->dingtalk_process_code,
            // 飞书配置
            'feishu_enabled' => $request->feishu_enabled ?? false,
            'feishu_app_id' => $request->feishu_app_id,
            'feishu_app_secret' => $request->feishu_app_secret,
            'feishu_verification_token' => $request->feishu_verification_token,
            'feishu_app_type' => $request->feishu_app_type ?? 'self',
            'feishu_approval_code' => $request->feishu_approval_code,
            // 企业微信配置
            'wework_enabled' => $request->wework_enabled ?? false,
            'wework_corp_id' => $request->wework_corp_id,
            'wework_secret' => $request->wework_secret,
            'wework_agent_id' => $request->wework_agent_id,
            'wework_token' => $request->wework_token,
            'wework_encoding_aes_key' => $request->wework_encoding_aes_key,
            'wework_approval_code' => $request->wework_approval_code,
            // IM通知渠道
            'im_notification_channel' => $request->im_notification_channel ?? 'log',
        ]);

        // 如果提供了管理员信息，同时创建租户管理员
        if ($request->admin_email && $request->admin_password) {
            User::create([
                'tenant_id' => $tenant->id,
                'name' => $request->admin_name ?: $request->admin_email,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'phone' => $request->admin_phone,
                'position' => '系统管理员',
                'is_admin' => 1,
                'is_super_admin' => 0,
                'status' => 1,
                'credit_score' => 100,
            ]);
        }

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
            
            // 管理员账号（可选）
            'admin_email' => 'nullable|email|max:100',
            'admin_password' => 'nullable|string|min:6',
            'admin_name' => 'nullable|string|max:50',
            'admin_phone' => 'nullable|string|max:20',
            
            // 钉钉配置
            'dingtalk_enabled' => 'nullable|boolean',
            'dingtalk_app_key' => 'nullable|string|max:100',
            'dingtalk_app_secret' => 'nullable|string|max:100',
            'dingtalk_corp_id' => 'nullable|string|max:100',
            'dingtalk_agent_id' => 'nullable|string|max:50',
            'dingtalk_process_code' => 'nullable|string|max:100',
            
            // 飞书配置
            'feishu_enabled' => 'nullable|boolean',
            'feishu_app_id' => 'nullable|string|max:100',
            'feishu_app_secret' => 'nullable|string|max:100',
            'feishu_verification_token' => 'nullable|string|max:255',
            'feishu_app_type' => 'nullable|in:self,store',
            'feishu_approval_code' => 'nullable|string|max:100',
            
            // 企业微信配置
            'wework_enabled' => 'nullable|boolean',
            'wework_corp_id' => 'nullable|string|max:100',
            'wework_secret' => 'nullable|string|max:100',
            'wework_agent_id' => 'nullable|string|max:50',
            'wework_token' => 'nullable|string|max:100',
            'wework_encoding_aes_key' => 'nullable|string|max:100',
            'wework_approval_code' => 'nullable|string|max:100',
            
            // IM通知渠道
            'im_notification_channel' => 'nullable|in:log,dingtalk,feishu,wework',
        ]);

        $tenant->update($request->only([
            'name', 'domain', 'contact_name', 'contact_phone',
            'contact_email', 'subscription_until', 'status',
            // 钉钉配置
            'dingtalk_enabled', 'dingtalk_app_key', 'dingtalk_app_secret',
            'dingtalk_corp_id', 'dingtalk_agent_id', 'dingtalk_process_code',
            // 飞书配置
            'feishu_enabled', 'feishu_app_id', 'feishu_app_secret', 'feishu_verification_token', 'feishu_app_type', 'feishu_approval_code',
            // 企业微信配置
            'wework_enabled', 'wework_corp_id', 'wework_secret',
            'wework_agent_id', 'wework_token', 'wework_encoding_aes_key', 'wework_approval_code',
            // IM通知渠道
            'im_notification_channel',
        ]));

        // 如果提供了管理员信息，更新或创建租户管理员
        if ($request->admin_email) {
            $admin = $tenant->users()->where('is_admin', 1)->first();
            
            $adminData = [
                'name' => $request->admin_name ?: $request->admin_email,
                'email' => $request->admin_email,
                'phone' => $request->admin_phone,
            ];
            
            // 如果提供了新密码，更新密码
            if ($request->admin_password) {
                $adminData['password'] = Hash::make($request->admin_password);
            }
            
            if ($admin) {
                $admin->update($adminData);
            } else {
                // 如果没有管理员，创建一个
                $adminData['tenant_id'] = $tenant->id;
                $adminData['position'] = '系统管理员';
                $adminData['is_admin'] = 1;
                $adminData['is_super_admin'] = 0;
                $adminData['status'] = 1;
                $adminData['credit_score'] = 100;
                User::create($adminData);
            }
        }

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

    /**
     * 获取当前租户的IM配置
     */
    public function getIMConfig(Request $request)
    {
        $user = $request->user();
        
        if (!$user->tenant_id) {
            return response()->json(['message' => '当前用户不属于任何租户'], 400);
        }

        $tenant = Tenant::findOrFail($user->tenant_id);

        return response()->json([
            'dingtalk_enabled' => $tenant->dingtalk_enabled,
            'dingtalk_corp_id' => $tenant->dingtalk_corp_id,
            'dingtalk_app_key' => $tenant->dingtalk_app_key,
            'dingtalk_app_secret' => $tenant->dingtalk_app_secret,
            'dingtalk_agent_id' => $tenant->dingtalk_agent_id,
            'dingtalk_process_code' => $tenant->dingtalk_process_code,
            'feishu_enabled' => $tenant->feishu_enabled,
            'feishu_app_id' => $tenant->feishu_app_id,
            'feishu_app_secret' => $tenant->feishu_app_secret,
            'feishu_verification_token' => $tenant->feishu_verification_token,
            'feishu_app_type' => $tenant->feishu_app_type,
            'feishu_approval_code' => $tenant->feishu_approval_code,
            'wework_enabled' => $tenant->wework_enabled,
            'wework_corp_id' => $tenant->wework_corp_id,
            'wework_secret' => $tenant->wework_secret,
            'wework_agent_id' => $tenant->wework_agent_id,
            'wework_token' => $tenant->wework_token,
            'wework_encoding_aes_key' => $tenant->wework_encoding_aes_key,
            'wework_approval_code' => $tenant->wework_approval_code,
            'im_notification_channel' => $tenant->im_notification_channel,
        ]);
    }

    /**
     * 更新当前租户的IM配置
     */
    public function updateIMConfig(Request $request)
    {
        $user = $request->user();
        
        if (!$user->tenant_id) {
            return response()->json(['message' => '当前用户不属于任何租户'], 400);
        }

        $tenant = Tenant::findOrFail($user->tenant_id);

        $request->validate([
            // 钉钉配置
            'dingtalk_enabled' => 'nullable|boolean',
            'dingtalk_corp_id' => 'nullable|string|max:100',
            'dingtalk_app_key' => 'nullable|string|max:100',
            'dingtalk_app_secret' => 'nullable|string|max:100',
            'dingtalk_agent_id' => 'nullable|string|max:50',
            'dingtalk_process_code' => 'nullable|string|max:100',
            
            // 飞书配置
            'feishu_enabled' => 'nullable|boolean',
            'feishu_app_id' => 'nullable|string|max:100',
            'feishu_app_secret' => 'nullable|string|max:100',
            'feishu_verification_token' => 'nullable|string|max:255',
            'feishu_app_type' => 'nullable|in:self,store',
            'feishu_approval_code' => 'nullable|string|max:100',
            
            // 企业微信配置
            'wework_enabled' => 'nullable|boolean',
            'wework_corp_id' => 'nullable|string|max:100',
            'wework_secret' => 'nullable|string|max:100',
            'wework_agent_id' => 'nullable|string|max:50',
            'wework_token' => 'nullable|string|max:100',
            'wework_encoding_aes_key' => 'nullable|string|max:100',
            'wework_approval_code' => 'nullable|string|max:100',
            
            // IM通知渠道
            'im_notification_channel' => 'nullable|in:log,dingtalk,feishu,wework',
        ]);

        $tenant->update($request->only([
            'dingtalk_enabled', 'dingtalk_corp_id', 'dingtalk_app_key',
            'dingtalk_app_secret', 'dingtalk_agent_id', 'dingtalk_process_code',
            'feishu_enabled', 'feishu_app_id', 'feishu_app_secret', 'feishu_verification_token', 'feishu_app_type', 'feishu_approval_code',
            'wework_enabled', 'wework_corp_id', 'wework_secret',
            'wework_agent_id', 'wework_token', 'wework_encoding_aes_key', 'wework_approval_code',
            'im_notification_channel',
        ]));

        return response()->json([
            'message' => 'IM配置更新成功',
            'data' => [
                'dingtalk_enabled' => $tenant->dingtalk_enabled,
                'dingtalk_corp_id' => $tenant->dingtalk_corp_id,
                'dingtalk_app_key' => $tenant->dingtalk_app_key,
                'dingtalk_app_secret' => $tenant->dingtalk_app_secret,
                'dingtalk_agent_id' => $tenant->dingtalk_agent_id,
                'dingtalk_process_code' => $tenant->dingtalk_process_code,
                'feishu_enabled' => $tenant->feishu_enabled,
                'feishu_app_id' => $tenant->feishu_app_id,
                'feishu_app_secret' => $tenant->feishu_app_secret,
                'feishu_verification_token' => $tenant->feishu_verification_token,
                'feishu_app_type' => $tenant->feishu_app_type,
                'feishu_approval_code' => $tenant->feishu_approval_code,
                'wework_enabled' => $tenant->wework_enabled,
                'wework_corp_id' => $tenant->wework_corp_id,
                'wework_secret' => $tenant->wework_secret,
                'wework_agent_id' => $tenant->wework_agent_id,
                'wework_token' => $tenant->wework_token,
                'wework_encoding_aes_key' => $tenant->wework_encoding_aes_key,
                'wework_approval_code' => $tenant->wework_approval_code,
                'im_notification_channel' => $tenant->im_notification_channel,
            ],
        ]);
    }
}
