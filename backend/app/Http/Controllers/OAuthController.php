<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use App\Services\IM\IMServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * OAuth登录控制器
 * 支持多租户架构，每个租户独立配置钉钉/飞书OAuth登录
 */
class OAuthController extends Controller
{
    /**
     * 获取OAuth配置（根据租户域名识别租户）
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOAuthConfig(Request $request)
    {
        try {
            // 通过域名识别租户
            $tenant = $this->getTenantByDomain($request);
            
            if (!$tenant) {
                return response()->json(['error' => '租户不存在'], 404);
            }

            $config = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'dingtalk_enabled' => $tenant->dingtalk_enabled,
                'feishu_enabled' => $tenant->feishu_enabled,
            ];

            // 钉钉配置
            if ($tenant->dingtalk_enabled) {
                $config['dingtalk'] = [
                    'corp_id' => $tenant->dingtalk_corp_id,
                    'redirect_uri' => url('/api/oauth/callback/dingtalk?tenant_id=' . $tenant->id),
                    'auth_url' => 'https://oapi.dingtalk.com/connect/qrconnect?appid=' . $tenant->dingtalk_app_key . 
                                  '&response_type=code&scope=snsapi_login&redirect_uri=' . 
                                  urlencode(url('/api/oauth/callback/dingtalk?tenant_id=' . $tenant->id)),
                ];
            }

            // 飞书配置
            if ($tenant->feishu_enabled) {
                $config['feishu'] = [
                    'app_id' => $tenant->feishu_app_id,
                    'redirect_uri' => url('/api/oauth/callback/feishu?tenant_id=' . $tenant->id),
                    'auth_url' => 'https://open.feishu.cn/open-apis/authen/v1/index?app_id=' . $tenant->feishu_app_id .
                                  '&redirect_uri=' . urlencode(url('/api/oauth/callback/feishu?tenant_id=' . $tenant->id)),
                ];
            }

            return response()->json($config);
        } catch (\Exception $e) {
            Log::error('[OAuth] 获取配置失败: ' . $e->getMessage());
            return response()->json(['error' => '获取配置失败'], 500);
        }
    }

    /**
     * 获取前端JSAPI配置
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJsApiConfig(Request $request)
    {
        try {
            $tenantId = $request->input('tenant_id');
            $url = $request->input('url');

            if (!$tenantId) {
                $tenant = $this->getTenantByDomain($request);
                $tenantId = $tenant ? $tenant->id : null;
            }

            if (!$tenantId) {
                return response()->json(['error' => '缺少租户信息'], 400);
            }

            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return response()->json(['error' => '租户不存在'], 404);
            }

            $config = [];

            if ($tenant->dingtalk_enabled) {
                $dingtalkService = IMServiceFactory::createDingtalkService($tenantId);
                $config['dingtalk'] = $dingtalkService->getJsApiConfig($url);
            }

            if ($tenant->feishu_enabled) {
                $feishuService = IMServiceFactory::createFeishuService($tenantId);
                $config['feishu'] = $feishuService->getJsApiConfig($url);
            }

            return response()->json($config);
        } catch (\Exception $e) {
            Log::error('[OAuth] 获取JSAPI配置失败: ' . $e->getMessage());
            return response()->json(['error' => '获取JSAPI配置失败'], 500);
        }
    }

    /**
     * 钉钉登录回调
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function dingtalkCallback(Request $request)
    {
        try {
            $code = $request->input('code');
            $tenantId = $request->input('tenant_id');

            if (!$code || !$tenantId) {
                return redirect('/login?error=missing_params');
            }

            $tenant = Tenant::find($tenantId);
            if (!$tenant || !$tenant->dingtalk_enabled) {
                return redirect('/login?error=tenant_not_enabled');
            }

            $dingtalkService = IMServiceFactory::createDingtalkService($tenantId);
            $userInfo = $dingtalkService->getUserByAuthCode($code);

            // 查找或创建用户
            $user = $this->findOrCreateUser($userInfo, $tenantId, 'DINGTALK');

            // 生成访问令牌
            $token = $user->createToken('dingtalk-oauth')->plainTextToken;

            // 重定向到前端，token 通过 hash 传递避免泄露到服务器日志
            return redirect('/oauth-success#' . http_build_query([
                'token' => $token,
                'tenant_id' => $tenantId,
            ]));
        } catch (\Exception $e) {
            Log::error('[OAuth] 钉钉登录失败: ' . $e->getMessage());
            return redirect('/login?error=oauth_failed');
        }
    }

    /**
     * 飞书登录回调
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function feishuCallback(Request $request)
    {
        try {
            $code = $request->input('code');
            $tenantId = $request->input('tenant_id');

            if (!$code || !$tenantId) {
                return redirect('/login?error=missing_params');
            }

            $tenant = Tenant::find($tenantId);
            if (!$tenant || !$tenant->feishu_enabled) {
                return redirect('/login?error=tenant_not_enabled');
            }

            $feishuService = IMServiceFactory::createFeishuService($tenantId);
            $userInfo = $feishuService->getUserByAuthCode($code);

            // 查找或创建用户
            $user = $this->findOrCreateUser($userInfo, $tenantId, 'FEISHU');

            // 生成访问令牌
            $token = $user->createToken('feishu-oauth')->plainTextToken;

            // 重定向到前端，token 通过 hash 传递避免泄露到服务器日志
            return redirect('/oauth-success#' . http_build_query([
                'token' => $token,
                'tenant_id' => $tenantId,
            ]));
        } catch (\Exception $e) {
            Log::error('[OAuth] 飞书登录失败: ' . $e->getMessage());
            return redirect('/login?error=oauth_failed');
        }
    }

    /**
     * 通过域名识别租户
     *
     * @param Request $request
     * @return Tenant|null
     */
    protected function getTenantByDomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        
        // 移除端口和协议
        $domain = preg_replace('/:\d+$/', '', $host);
        
        // 尝试通过域名匹配租户
        $tenant = Tenant::where('domain', $domain)->first();
        
        if (!$tenant) {
            // 尝试匹配子域名（如 tenant1.meeting.sisuu.com）
            $parts = explode('.', $domain);
            if (count($parts) >= 3) {
                $subdomain = $parts[0];
                $tenant = Tenant::where('domain', 'LIKE', '%' . $subdomain . '%')->first();
            }
        }
        
        return $tenant;
    }

    /**
     * 查找或创建用户
     *
     * @param array $userInfo IM用户信息
     * @param int $tenantId 租户ID
     * @param string $source 来源（DINGTALK/FEISHU）
     * @return User
     */
    protected function findOrCreateUser(array $userInfo, int $tenantId, string $source): User
    {
        $userIdField = $source === 'DINGTALK' ? 'dingtalk_user_id' : 'feishu_open_id';
        $imUserId = $userInfo['user_id'];

        // 先通过IM用户ID查找
        $user = User::where($userIdField, $imUserId)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if ($user) {
            // 更新用户信息
            $user->update([
                'name' => $userInfo['name'] ?? $user->name,
                'phone' => $userInfo['phone'] ?? $user->phone,
                'email' => $userInfo['email'] ?? $user->email,
            ]);
            return $user;
        }

        // 尝试通过手机号或邮箱匹配
        $phone = $userInfo['phone'] ?? null;
        $email = $userInfo['email'] ?? null;

        if ($phone) {
            $user = User::where('phone', $phone)
                        ->where('tenant_id', $tenantId)
                        ->first();
        }

        if (!$user && $email) {
            $user = User::where('email', $email)
                        ->where('tenant_id', $tenantId)
                        ->first();
        }

        if ($user) {
            // 关联IM用户ID
            $user->update([
                $userIdField => $imUserId,
                'source' => $source,
                'name' => $userInfo['name'] ?? $user->name,
            ]);
            return $user;
        }

        // 创建新用户
        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $userInfo['name'] ?? 'IM用户',
            'email' => $email ?? null,
            'phone' => $phone ?? null,
            $userIdField => $imUserId,
            'source' => $source,
            'position' => $userInfo['position'] ?? '',
            'credit_score' => 100,
            'status' => true,
        ]);

        Log::info('[OAuth] 创建新用户: ' . $user->id . ' 来源: ' . $source);

        return $user;
    }
}