<?php

namespace App\Services\IM;

use App\Models\Tenant;

abstract class AbstractIMService implements IMService
{
    protected ?int $tenantId = null;
    protected ?Tenant $tenant = null;
    protected ?string $accessToken = null;
    protected int $tokenExpiresAt = 0;

    public function setTenant(int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->tenant = Tenant::find($tenantId);
        $this->accessToken = null;
        $this->tokenExpiresAt = 0;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    protected function getCachedToken(): ?string
    {
        if ($this->accessToken && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        return null;
    }

    protected function cacheToken(string $token, int $expiresIn): void
    {
        $this->accessToken = $token;
        $this->tokenExpiresAt = time() + $expiresIn - 60;
    }

    protected function generateJsSignature(string $ticket, string $nonceStr, int $timestamp, string $url): string
    {
        $plainText = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        return sha1($plainText);
    }

    abstract public function isEnabled(): bool;
    abstract public function getAccessToken(): string;
}
