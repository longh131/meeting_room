<?php

namespace App\Services\IM;

/**
 * IM服务接口
 * 定义统一的IM操作方法，钉钉和飞书分别实现此接口
 * 支持多租户架构，每个租户独立配置IM应用
 */
interface IMService
{
    /**
     * 设置当前租户
     *
     * @param int $tenantId 租户ID
     * @return void
     */
    public function setTenant(int $tenantId): void;

    /**
     * 获取当前租户ID
     *
     * @return int|null
     */
    public function getTenantId(): ?int;

    /**
     * 通过授权码获取用户信息
     *
     * @param string $code 授权码
     * @return array 用户信息数组
     */
    public function getUserByAuthCode(string $code): array;

    /**
     * 同步组织架构
     *
     * @param bool $fullSync 是否全量同步（默认增量）
     * @return bool 同步是否成功
     */
    public function syncOrganization(bool $fullSync = false): bool;

    /**
     * 发起审批
     *
     * @param array $booking 预定信息
     * @return array 审批结果
     */
    public function sendApproval(array $booking): array;

    /**
     * 处理审批回调
     *
     * @param array $data 回调数据
     * @return array 处理结果
     */
    public function handleApprovalCallback(array $data): array;

    /**
     * 发送消息通知
     *
     * @param string $userId 用户ID（IM平台的用户标识）
     * @param string $message 消息内容
     * @param string $type 消息类型
     * @return bool 发送是否成功
     */
    public function sendNotification(string $userId, string $message, string $type = 'text'): bool;

    /**
     * 获取前端JSAPI配置
     *
     * @param string $url 当前页面URL
     * @return array JSAPI配置
     */
    public function getJsApiConfig(string $url): array;

    /**
     * 获取部门列表
     *
     * @param bool $withUsers 是否包含用户
     * @return array 部门树结构
     */
    public function getDepartments(bool $withUsers = false): array;

    /**
     * 获取用户详情
     *
     * @param string $userId 用户ID
     * @return array 用户信息
     */
    public function getUserInfo(string $userId): array;

    /**
     * 获取访问令牌
     *
     * @return string 访问令牌
     */
    public function getAccessToken(): string;

    /**
     * 检查租户是否启用了该IM服务
     *
     * @return bool
     */
    public function isEnabled(): bool;
}