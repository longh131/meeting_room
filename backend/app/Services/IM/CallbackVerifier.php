<?php

namespace App\Services\IM;

use App\Models\Tenant;

/**
 * IM 回调签名校验
 */
class CallbackVerifier
{
    /**
     * 钉钉回调签名校验（事件订阅 Token 模式）
     */
    public static function verifyDingtalk(Tenant $tenant, ?string $timestamp, ?string $nonce, ?string $signature): bool
    {
        $token = $tenant->dingtalk_callback_token;

        if (empty($token)) {
            return false;
        }

        if (empty($timestamp) || empty($nonce) || empty($signature)) {
            return false;
        }

        $arr = [$token, $timestamp, $nonce];
        sort($arr, SORT_STRING);
        $expected = sha1(implode('', $arr));

        return hash_equals($expected, $signature);
    }

    /**
     * 飞书 URL 验证 / 事件 Token 校验
     *
     * @return array{type: string, challenge?: string}
     */
    public static function handleFeishu(Tenant $tenant, array $data): array
    {
        if (($data['type'] ?? '') === 'url_verification') {
            $token = $data['token'] ?? '';
            if (!empty($tenant->feishu_verification_token) && $token !== $tenant->feishu_verification_token) {
                return ['type' => 'error'];
            }

            return [
                'type' => 'challenge',
                'challenge' => $data['challenge'] ?? '',
            ];
        }

        $receivedToken = $data['token'] ?? ($data['header']['token'] ?? '');
        if (!empty($tenant->feishu_verification_token) &&
            !empty($receivedToken) &&
            $receivedToken !== $tenant->feishu_verification_token) {
            return ['type' => 'error'];
        }

        return ['type' => 'ok'];
    }
}
