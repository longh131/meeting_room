<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\IM\CallbackVerifier;
use App\Services\IM\IMBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IMBotController extends Controller
{
    public function dingtalk(Request $request, IMBotService $botService)
    {
        try {
            $tenantId = (int) $request->input('tenant_id');
            $tenant = Tenant::find($tenantId);

            if (!$tenant || !$tenant->dingtalk_enabled) {
                return response()->json(['errcode' => -1, 'errmsg' => '租户未启用钉钉']);
            }

            if (!empty($tenant->dingtalk_callback_token)) {
                $signature = $request->input('signature') ?? $request->header('signature');
                $timestamp = $request->input('timestamp') ?? $request->header('timestamp');
                $nonce = $request->input('nonce') ?? $request->header('nonce');
                if (!CallbackVerifier::verifyDingtalk($tenant, $timestamp, $nonce, $signature)) {
                    return response()->json(['errcode' => -1, 'errmsg' => '签名校验失败'], 403);
                }
            }

            $text = $request->input('text.content')
                ?? $request->input('text')
                ?? $request->input('content')
                ?? '';

            $senderId = $request->input('senderStaffId')
                ?? $request->input('senderId')
                ?? $request->input('userid');

            $result = $botService->handle($tenantId, $text, $senderId, 'dingtalk');

            return response()->json([
                'msgtype' => 'text',
                'text' => ['content' => $result['message']],
            ]);
        } catch (\Exception $e) {
            Log::error('[IMBot Dingtalk] ' . $e->getMessage());
            return response()->json(['errcode' => -1, 'errmsg' => '处理失败']);
        }
    }

    public function feishu(Request $request, IMBotService $botService)
    {
        try {
            $data = $request->all();
            $tenantId = (int) $request->input('tenant_id');
            $tenant = Tenant::find($tenantId);

            if (!$tenant || !$tenant->feishu_enabled) {
                return response()->json(['code' => -1, 'msg' => '租户未启用飞书']);
            }

            $verify = CallbackVerifier::handleFeishu($tenant, $data);
            if ($verify['type'] === 'error') {
                return response()->json(['code' => -1, 'msg' => 'Token校验失败'], 403);
            }
            if ($verify['type'] === 'challenge') {
                return response()->json(['challenge' => $verify['challenge']]);
            }

            $event = $data['event'] ?? $data;
            $text = $event['message']['content'] ?? $event['text'] ?? '';
            if (is_string($text) && str_starts_with($text, '{')) {
                $decoded = json_decode($text, true);
                $text = $decoded['text'] ?? $text;
            }
            $text = trim($text);

            $senderId = $event['sender']['sender_id']['open_id']
                ?? $event['open_id']
                ?? null;

            $result = $botService->handle($tenantId, $text, $senderId, 'feishu');

            return response()->json([
                'msg_type' => 'text',
                'content' => ['text' => $result['message']],
            ]);
        } catch (\Exception $e) {
            Log::error('[IMBot Feishu] ' . $e->getMessage());
            return response()->json(['code' => -1, 'msg' => '处理失败']);
        }
    }
}
