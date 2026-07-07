<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Crypt;

/**
 * IM 密钥加密辅助（写入时加密，读取时兼容历史明文）
 */
trait EncryptsImSecrets
{
    protected function encryptSecret(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    protected function decryptSecret(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function getDingtalkAppSecretAttribute($value)
    {
        return $this->decryptSecret($value);
    }

    public function setDingtalkAppSecretAttribute($value): void
    {
        $this->attributes['dingtalk_app_secret'] = $this->encryptSecret($value);
    }

    public function getFeishuAppSecretAttribute($value)
    {
        return $this->decryptSecret($value);
    }

    public function setFeishuAppSecretAttribute($value): void
    {
        $this->attributes['feishu_app_secret'] = $this->encryptSecret($value);
    }

    public function getFeishuVerificationTokenAttribute($value)
    {
        return $this->decryptSecret($value);
    }

    public function setFeishuVerificationTokenAttribute($value): void
    {
        $this->attributes['feishu_verification_token'] = $this->encryptSecret($value);
    }

    public function getWeworkSecretAttribute($value)
    {
        return $this->decryptSecret($value);
    }

    public function setWeworkSecretAttribute($value): void
    {
        $this->attributes['wework_secret'] = $this->encryptSecret($value);
    }
}
