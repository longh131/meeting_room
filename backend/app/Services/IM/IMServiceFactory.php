<?php

namespace App\Services\IM;

use App\Models\Tenant;

/**
 * IM服务工厂类
 * 根据租户配置创建对应的IM服务实例
 */
class IMServiceFactory
{
    /**
     * 创建钉钉服务实例（带租户配置）
     *
     * @param int $tenantId 租户ID
     * @return DingtalkService
     */
    public static function createDingtalkService(int $tenantId): DingtalkService
    {
        $service = new DingtalkService();
        $service->setTenant($tenantId);
        return $service;
    }

    /**
     * 创建飞书服务实例（带租户配置）
     *
     * @param int $tenantId 租户ID
     * @return FeishuService
     */
    public static function createFeishuService(int $tenantId): FeishuService
    {
        $service = new FeishuService();
        $service->setTenant($tenantId);
        return $service;
    }

    /**
     * 根据租户配置获取启用的IM服务
     *
     * @param int $tenantId 租户ID
     * @return IMService|null
     */
    public static function getEnabledService(int $tenantId): ?IMService
    {
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            return null;
        }

        // 优先返回钉钉服务（如果启用）
        if ($tenant->dingtalk_enabled) {
            return self::createDingtalkService($tenantId);
        }

        // 其次返回飞书服务（如果启用）
        if ($tenant->feishu_enabled) {
            return self::createFeishuService($tenantId);
        }

        return null;
    }

    /**
     * 根据用户来源获取对应的IM服务
     *
     * @param int $tenantId 租户ID
     * @param string $source 用户来源（DINGTALK/FEISHU）
     * @return IMService|null
     */
    public static function getServiceBySource(int $tenantId, string $source): ?IMService
    {
        switch ($source) {
            case 'DINGTALK':
                return self::createDingtalkService($tenantId);
            case 'FEISHU':
                return self::createFeishuService($tenantId);
            default:
                return null;
        }
    }

    /**
     * 获取租户的通知渠道配置
     *
     * @param int $tenantId 租户ID
     * @return string 通知渠道（log/dingtalk/feishu）
     */
    public static function getNotificationChannel(int $tenantId): string
    {
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            return 'log';
        }

        return $tenant->im_notification_channel ?? 'log';
    }
}