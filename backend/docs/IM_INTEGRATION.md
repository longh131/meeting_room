# 会议室预定系统 - IM平台对接文档（多租户版）

## 概述

本文档描述会议室预定系统与钉钉、飞书平台的对接方案，**支持多租户架构，每个租户独立配置IM应用**。

---

## 一、多租户架构说明

### 1.1 核心设计

本系统采用**租户级别IM配置**，每个租户可以独立配置自己的钉钉或飞书应用：

- 每个租户在`tenants`表中存储独立的IM配置（AppKey、AppSecret等）
- IM服务通过`setTenant($tenantId)`方法切换租户配置
- OAuth登录通过域名或参数识别租户
- 审批回调需要携带`tenant_id`参数

### 1.2 配置存储位置

| 配置项 | 存储位置 | 说明 |
|--------|----------|------|
| 钉钉AppKey | tenants.dingtalk_app_key | 租户级别配置 |
| 钉钉AppSecret | tenants.dingtalk_app_secret | 租户级别配置 |
| 钉钉CorpId | tenants.dingtalk_corp_id | 租户级别配置 |
| 钉钉AgentId | tenants.dingtalk_agent_id | 租户级别配置 |
| 钉钉ProcessCode | tenants.dingtalk_process_code | 租户审批流程编码 |
| 钉钉启用状态 | tenants.dingtalk_enabled | 是否启用 |
| 飞书AppId | tenants.feishu_app_id | 租户级别配置 |
| 飞书AppSecret | tenants.feishu_app_secret | 租户级别配置 |
| 飞书ApprovalCode | tenants.feishu_approval_code | 租户审批定义Code |
| 飞书启用状态 | tenants.feishu_enabled | 是否启用 |
| 通知渠道 | tenants.im_notification_channel | log/dingtalk/feishu |

---

## 二、数据库变更

### 2.1 tenants表新增字段

执行迁移：

```bash
cd backend
php artisan migrate
```

新增字段：

| 字段名 | 类型 | 说明 |
|--------|------|------|
| dingtalk_enabled | boolean | 是否启用钉钉 |
| dingtalk_app_key | varchar(255) | 钉钉应用AppKey |
| dingtalk_app_secret | varchar(255) | 钉钉应用AppSecret |
| dingtalk_corp_id | varchar(255) | 钉钉企业CorpId |
| dingtalk_agent_id | varchar(255) | 钉钉应用AgentId |
| dingtalk_process_code | varchar(255) | 钉钉审批流程Code |
| feishu_enabled | boolean | 是否启用飞书 |
| feishu_app_id | varchar(255) | 飞书应用AppId |
| feishu_app_secret | varchar(255) | 飞书应用AppSecret |
| feishu_approval_code | varchar(255) | 飞书审批定义Code |
| im_notification_channel | varchar(20) | IM通知渠道 |

### 2.2 users表新增字段

| 字段名 | 类型 | 说明 |
|--------|------|------|
| source | varchar(20) | 注册来源：LOCAL/DINGTALK/FEISHU |
| dingtalk_user_id | varchar(64) | 钉钉用户ID |
| feishu_open_id | varchar(64) | 飞书用户OpenId |

---

## 三、租户IM配置管理

### 3.1 配置入口

租户管理员在后台管理界面配置IM信息：

**API接口：**

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/admin/tenants/{id}` | PUT | 更新租户IM配置 |

**请求示例：**

```json
{
    "dingtalk_enabled": true,
    "dingtalk_app_key": "dingXXXXXX",
    "dingtalk_app_secret": "XXXXXX",
    "dingtalk_corp_id": "dingXXXXXX",
    "dingtalk_agent_id": "123456",
    "dingtalk_process_code": "PROC-XXXXXX",
    "im_notification_channel": "dingtalk"
}
```

### 3.2 配置验证

配置保存后，系统自动验证：

1. 检查AppKey/AppSecret是否有效
2. 尝试获取access_token
3. 验证AgentId是否存在
4. 验证审批流程Code是否有效

---

## 四、钉钉开放平台配置（每个租户独立）

### 4.1 创建钉钉应用

每个租户需要在自己的钉钉企业中创建应用：

1. 登录 [钉钉开放平台](https://open-dev.dingtalk.com/)
2. 进入「应用开发」→「企业内部应用」
3. 创建新应用

### 4.2 应用权限配置

| 权限名称 | 权限码 | 说明 |
|----------|--------|------|
| 通讯录只读权限 | contacts:org:readonly | 获取部门和用户信息 |
| 企业消息发送 | im:message:corpconversation | 发送工作消息 |
| 审批单管理 | process:processinstance | 发起审批 |

### 4.3 回调URL配置

**重要：回调URL必须携带tenant_id参数**

| 回调类型 | URL格式 |
|----------|---------|
| 审批回调 | `http://your-domain.com/api/callback/approval/dingtalk?tenant_id={租户ID}` |

示例：
```
http://meeting.sisuu.com/api/callback/approval/dingtalk?tenant_id=1
```

### 4.4 OAuth登录配置

- 回调域名：`your-domain.com`
- 回调URL：`http://your-domain.com/api/oauth/callback/dingtalk?tenant_id={租户ID}`

---

## 五、飞书开放平台配置（每个租户独立）

### 5.1 创建飞书应用

每个租户需要在自己的飞书企业中创建应用：

1. 登录 [飞书开放平台](https://open.feishu.cn/)
2. 进入「应用管理」→「创建企业自建应用」

### 5.2 应用权限配置

| 权限名称 | 权限码 | 说明 |
|----------|--------|------|
| 获取部门基础信息 | contact:department:readonly | 获取部门列表 |
| 获取用户基础信息 | contact:user:readonly | 获取用户信息 |
| 发送单聊消息 | im:message:send | 发送私信消息 |
| 审批流程管理 | approval:approval | 发起审批 |

### 5.3 事件订阅配置

**重要：回调URL必须携带tenant_id参数**

| 事件类型 | URL格式 |
|----------|---------|
| 审批状态变更 | `http://your-domain.com/api/callback/approval/feishu?tenant_id={租户ID}` |

---

## 六、API接口清单

### 6.1 OAuth登录接口

| 接口 | 方法 | 说明 | 参数 |
|------|------|------|------|
| `/api/oauth/config` | GET | 获取OAuth配置（自动识别租户） | 通过域名识别 |
| `/api/oauth/jsapi-config` | GET | 获取JSAPI配置 | tenant_id, url |
| `/api/oauth/callback/dingtalk` | GET | 钉钉登录回调 | code, tenant_id |
| `/api/oauth/callback/feishu` | GET | 飞书登录回调 | code, tenant_id |

### 6.2 组织架构接口

| 接口 | 方法 | 说明 | 认证 |
|------|------|------|------|
| `/api/organization/department-tree` | GET | 获取部门树结构 | 需要 |
| `/api/organization/users-by-department` | GET | 获取部门用户列表 | 需要 |
| `/api/organization/sync` | POST | 同步组织架构 | 需要 |
| `/api/organization/sync-to-local` | POST | 全量同步到本地 | 需要 |

### 6.3 审批回调接口

| 接口 | 方法 | 说明 | 参数 |
|------|------|------|------|
| `/api/callback/approval/dingtalk` | POST | 钉钉审批回调 | tenant_id（必需） |
| `/api/callback/approval/feishu` | POST | 飞书审批回调 | tenant_id（必需） |
| `/api/callback/verify` | GET | 回调URL验证 | - |

---

## 七、租户识别方式

### 7.1 通过域名识别

系统支持通过访问域名自动识别租户：

```
tenant1.meeting.sisuu.com → 租户1
tenant2.meeting.sisuu.com → 租户2
```

### 7.2 通过参数识别

OAuth和回调接口通过`tenant_id`参数识别：

```
/api/oauth/callback/dingtalk?tenant_id=1
/api/callback/approval/dingtalk?tenant_id=1
```

---

## 八、定时任务配置

### 8.1 组织架构同步

系统会为每个启用了IM的租户定时同步组织架构：

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // 每天凌晨2点全量同步所有租户的组织架构
    $schedule->command('organization:sync --full')->dailyAt('02:00');
}
```

### 8.2 手动同步

租户管理员可以手动触发同步：

```bash
# 同步指定租户
php artisan organization:sync --tenant=1

# 同步所有租户
php artisan organization:sync --all
```

---

## 九、消息通知推送

### 9.1 推送节点

| 节点 | 触发时机 | 通知类型 |
|------|----------|----------|
| 预定成功 | 创建预定时 | reservation_create |
| 审批通过 | 审批通过时 | reservation_approve |
| 审批驳回 | 审批驳回时 | reservation_reject |
| 会前提醒 | 会议开始前15分钟 | meeting_remind |
| 签到提醒 | 会议开始后 | checkin_remind |

### 9.2 通知渠道选择

系统根据以下规则选择通知渠道：

1. 优先使用租户配置的`im_notification_channel`
2. 如果租户未配置，则根据用户`source`字段选择
3. 如果以上都未配置，则降级为日志记录

---

## 十、安全注意事项

1. **租户隔离**：确保每个租户的IM配置独立存储，不可互相访问
2. **回调验证**：审批回调必须验证`tenant_id`参数
3. **Token管理**：每个租户的access_token独立缓存
4. **权限控制**：租户管理员才能配置IM信息

---

## 十一、故障排查

### 11.1 常见问题

| 问题 | 可能原因 | 解决方案 |
|------|----------|----------|
| OAuth登录失败 | 租户未启用IM | 检查tenant.dingtalk_enabled |
| 消息推送失败 | 租户配置错误 | 检查AppKey/AppSecret |
| 审批回调收不到 | 缺少tenant_id | 确保回调URL携带tenant_id |
| 组织架构同步失败 | 租户权限不足 | 检查IM应用权限配置 |

### 11.2 日志查看

```bash
# 查看租户相关日志
grep "租户" storage/logs/laravel.log

# 查看IM相关日志
grep -E "(Dingtalk|Feishu)" storage/logs/laravel.log
```

---

## 附录：架构流程图

```
┌─────────────────────────────────────────────────────────────────┐
│                    多租户IM对接架构                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐         │
│  │   租户A     │    │   租户B     │    │   租户C     │         │
│  │ 钉钉配置    │    │ 钞书配置    │    │ 未启用IM    │         │
│  └─────────────┘    └─────────────┘    └─────────────┘         │
│        ↓                  ↓                  ↓                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              IMServiceFactory                            │   │
│  │  createDingtalkService($tenantId)                        │   │
│  │  createFeishuService($tenantId)                          │   │
│  │  getEnabledService($tenantId)                            │   │
│  └─────────────────────────────────────────────────────────┘   │
│        ↓                  ↓                                    │
│  ┌─────────────┐    ┌─────────────┐                           │
│  │DingtalkSvc  │    │ FeishuSvc   │                           │
│  │setTenant()  │    │setTenant()  │                           │
│  └─────────────┘    └─────────────┘                           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        OAuth登录流程                            │
├─────────────────────────────────────────────────────────────────┤
│  用户访问 → 域名识别租户 → 获取租户IM配置 → 生成OAuth链接         │
│       ↓                                                        │
│  用户扫码 → IM平台回调（携带tenant_id）→ 获取用户信息              │
│       ↓                                                        │
│  查找/创建本地用户 → 生成Token → 返回前端                         │
└─────────────────────────────────────────────────────────────────┘
```