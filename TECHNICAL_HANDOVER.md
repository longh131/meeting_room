# 会议室预定系统 - 技术移交文档

---

## 一、项目概述

### 1.1 项目背景
会议室预定系统是一个企业级多租户会议室管理平台，旨在帮助企业高效管理会议室资源，实现预定、审批、签到等全流程数字化管理。系统支持对接钉钉、飞书、企业微信等主流IM平台，实现免密登录、组织架构同步、审批流集成、消息通知推送等功能。

### 1.2 功能定位
- **核心功能**：会议室管理、预定管理、审批管理、签到管理、组织架构管理
- **IM集成**：支持钉钉、飞书、企业微信的OAuth登录、审批回调、消息通知
- **多租户架构**：支持多企业独立使用，数据完全隔离
- **移动端适配**：支持Pad端会议室展示和扫码签到

### 1.3 系统架构图

```
┌─────────────────────────────────────────────────────────────────────┐
│                        客户端层                                     │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │
│  │  PC Web  │  │  Pad端   │  │ 钉钉H5   │  │ 飞书/企微H5      │   │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────────┬─────────┘   │
└───────┼─────────────┼─────────────┼─────────────────┼──────────────┘
        │             │             │                 │
        ▼             ▼             ▼                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Nginx 反向代理                               │
│  /api/* → http://127.0.0.1:8000 (后端API)                          │
│  /* → /www/wwwroot/meeting.sisuu.com/frontend/dist (前端静态资源)   │
└─────────────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        应用层 (Laravel 11)                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐  │
│  │   认证模块    │  │   业务模块    │  │   IM服务模块            │  │
│  │ AuthController│  │ 各类Controller│  │ IMService/DingtalkService││
│  │ OAuthController│ │              │  │ FeishuService/WeworkService│
│  └──────────────┘  └──────────────┘  └──────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        数据层 (MySQL 8.0)                           │
│  tenants | users | meeting_rooms | reservations | approvals        │
│  departments | notification_logs | favorites | device_tags         │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 二、技术栈

### 2.1 前端技术栈
| 技术 | 版本 | 用途 |
|------|------|------|
| Vue | 3.4.21 | 前端框架 |
| Element Plus | 2.6.3 | UI组件库 |
| Vue Router | 4.3.0 | 路由管理 |
| Vuex | 4.1.0 | 状态管理 |
| Vite | 5.2.8 | 构建工具 |
| Axios | 1.6.8 | HTTP请求 |
| Dayjs | 1.11.10 | 日期处理 |
| QRCode | 1.5.3 | 二维码生成 |
| Sass | 1.72.0 | CSS预处理 |

### 2.2 后端技术栈
| 技术 | 版本 | 用途 |
|------|------|------|
| Laravel | 11+ | 后端框架 |
| PHP | 8.2 | 编程语言 |
| MySQL | 8.0+ | 数据库 |
| Laravel Sanctum | - | API认证 |
| Laravel HTTP Client | - | HTTP请求 |

### 2.3 基础设施
| 服务 | 用途 |
|------|------|
| Nginx | Web服务器/反向代理 |
| php-fpm | PHP进程管理（生产环境） |
| BaoTa Panel | 服务器管理面板 |

### 2.4 状态管理
- **前端**：Vuex 4.1.0（非Pinia，注意区分）

---

## 三、项目结构

### 3.1 前端目录结构

```
frontend/
├── src/
│   ├── views/                    # 页面视图
│   │   ├── admin/                # 租户管理员页面
│   │   │   ├── Users.vue         # 用户管理
│   │   │   ├── MeetingRooms.vue  # 会议室管理
│   │   │   ├── DeviceTags.vue    # 设备标签管理
│   │   │   ├── Departments.vue   # 部门管理
│   │   │   └── IMConfig.vue      # IM配置
│   │   ├── super-admin/          # 超级管理员页面
│   │   │   ├── Dashboard.vue     # 仪表盘
│   │   │   ├── Tenants.vue       # 租户列表
│   │   │   ├── TenantDetail.vue  # 租户详情
│   │   │   └── Reports.vue       # 统计报表
│   │   ├── Login.vue             # 登录页面
│   │   ├── Home.vue              # 首页
│   │   ├── Dashboard.vue         # 用户仪表盘
│   │   ├── MeetingRooms.vue      # 会议室列表
│   │   ├── MeetingRoomDetail.vue # 会议室详情
│   │   ├── CreateReservation.vue # 创建预定
│   │   ├── Reservations.vue      # 预定列表
│   │   ├── ReservationDetail.vue # 预定详情
│   │   ├── Approvals.vue         # 审批列表
│   │   ├── Reports.vue           # 报表
│   │   ├── Profile.vue           # 个人资料
│   │   ├── PadDisplay.vue        # Pad展示页面
│   │   └── Checkin.vue           # 签到页面
│   ├── router/
│   │   └── index.js              # 路由配置
│   ├── store/
│   │   └── index.js              # Vuex状态管理
│   ├── utils/
│   │   └── axios.js              # Axios封装
│   ├── App.vue                   # 根组件
│   └── main.js                   # 入口文件
├── public/                       # 静态资源
├── dist/                         # 构建产物
├── vite.config.js                # Vite配置
└── package.json                  # 依赖配置
```

### 3.2 后端目录结构

```
backend/
├── app/
│   ├── Http/
│   │   └── Controllers/          # 控制器
│   │       ├── AuthController.php         # 认证
│   │       ├── OAuthController.php        # OAuth登录
│   │       ├── MeetingRoomController.php  # 会议室
│   │       ├── ReservationController.php  # 预定
│   │       ├── ApprovalController.php     # 审批
│   │       ├── ApprovalCallbackController.php # 审批回调
│   │       ├── TenantController.php       # 租户
│   │       ├── UserController.php         # 用户
│   │       ├── DepartmentController.php   # 部门
│   │       ├── OrganizationController.php # 组织架构同步
│   │       ├── ReportController.php       # 报表
│   │       ├── AdminReportController.php  # 管理员报表
│   │       ├── DeviceTagController.php    # 设备标签
│   │       └── FavoriteController.php     # 收藏
│   ├── Models/                   # 模型
│   │   ├── Tenant.php              # 租户
│   │   ├── User.php                # 用户
│   │   ├── MeetingRoom.php         # 会议室
│   │   ├── Reservation.php         # 预定
│   │   ├── Approval.php            # 审批
│   │   ├── Department.php          # 部门
│   │   ├── Favorite.php            # 收藏
│   │   ├── DeviceTag.php           # 设备标签
│   │   └── NotificationLog.php     # 通知日志
│   ├── Services/                 # 服务层
│   │   ├── IM/                   # IM服务
│   │   │   ├── IMService.php          # IM服务接口
│   │   │   ├── IMServiceFactory.php    # IM服务工厂
│   │   │   ├── DingtalkService.php     # 钉钉服务
│   │   │   ├── FeishuService.php       # 飞书服务
│   │   │   └── WeworkService.php       # 企业微信服务
│   │   └── NotificationService.php     # 通知服务
│   ├── Middleware/               # 中间件
│   │   └── CheckTenant.php        # 租户检查
│   └── Providers/                # 服务提供者
├── routes/
│   ├── api.php                   # API路由
│   └── web.php                   # Web路由
├── database/
│   ├── migrations/               # 数据库迁移
│   └── seeders/                  # 数据填充
├── config/                       # 配置文件
├── storage/                      # 存储目录
├── public/                       # 公开目录
├── .env                          # 环境配置
└── artisan                       # Laravel命令行工具
```

### 3.3 关键文件说明

| 文件 | 说明 |
|------|------|
| `frontend/src/utils/axios.js` | Axios封装，统一处理请求拦截、响应拦截、baseURL配置 |
| `frontend/src/router/index.js` | 路由配置，包含权限控制（requiresAuth/requiresAdmin/requiresSuperAdmin） |
| `frontend/src/store/index.js` | Vuex状态管理，管理用户登录状态、设备标签 |
| `backend/app/Services/IM/IMService.php` | IM服务接口定义，所有IM服务必须实现此接口 |
| `backend/app/Services/IM/IMServiceFactory.php` | IM服务工厂，根据租户配置创建对应的IM服务实例 |
| `backend/app/Http/Controllers/ApprovalCallbackController.php` | 审批回调控制器，处理钉钉/飞书/企业微信审批结果回调 |
| `backend/routes/api.php` | API路由定义，包含认证、业务、回调等路由 |
| `backend/.env` | 环境配置文件，包含数据库连接、IM配置等 |

---

## 四、核心功能模块

### 4.1 用户认证模块

#### 4.1.1 本地账号登录
- **接口**：`POST /api/login`
- **流程**：用户输入邮箱和密码 → 验证身份 → 返回JWT token
- **测试账号**：
  - 超级管理员：`super@admin.com` / `123456`
  - 租户1管理员：`admin@demo.com` / `123456`
  - 租户2管理员：`admin@inno.com` / `123456`
  - 租户1用户：`zhangsan@demo.com` / `123456`

#### 4.1.2 IM OAuth登录
- **钉钉**：`GET /api/oauth/callback/dingtalk`
- **飞书**：`GET /api/oauth/callback/feishu`
- **流程**：用户通过IM扫码 → 获取授权码 → 获取用户信息 → 匹配本地用户（手机号/邮箱）→ 自动创建或关联用户 → 返回JWT token

### 4.2 会议室管理模块

#### 4.2.1 会议室CRUD
- 租户管理员可创建、编辑、删除会议室
- 支持按楼层管理会议室
- 支持会议室设备标签（投影仪、白板等）

#### 4.2.2 可用性检查
- 预定前检查会议室是否有冲突
- 支持实时查询会议室状态

#### 4.2.3 Pad页面访问
- **URL格式**：`http://meeting.sisuu.com/pad/{access_code}`
- **安全机制**：使用8位随机字符串（大小写字母+数字）作为`access_code`，替代会议室ID，实现租户隔离
- **页面功能**：显示会议室当前和即将进行的会议，支持扫码签到

### 4.3 预定模块

#### 4.3.1 预定创建与编辑
- 支持选择会议室、时间、参会人员
- 自动检查时间冲突

#### 4.3.2 状态流转
```
创建 → 待审批 → 已审批 → 进行中 → 已结束
         ↓
      已拒绝
```

#### 4.3.3 冲突检测
- 同一会议室同一时间段只能有一个有效预定
- 预定创建时自动检测并提示冲突

### 4.4 审批模块

#### 4.4.1 审批流程
- 需要审批的预定提交后，发送审批请求到IM平台
- 审批人在IM中审批，结果通过回调接口回传

#### 4.4.2 IM审批回调
- **钉钉**：`POST /api/callback/approval/dingtalk`
- **飞书**：`POST /api/callback/approval/feishu`
- **企业微信**：`POST /api/callback/approval/wework`
- 回调数据包含`tenant_id`参数，用于区分不同租户

### 4.5 签到模块

#### 4.5.1 Pad签到
- 在Pad页面显示当前会议的签到二维码
- 用户扫码后验证身份和参会权限

#### 4.5.2 签到时间
- 会议开始前30分钟至会议结束时间内可签到
- 验证用户是否为会议组织者或参会人员

#### 4.5.3 签到方式
- 支持钉钉/飞书/企业微信扫码验证身份
- 支持手动输入工号签到

### 4.6 组织架构模块

#### 4.6.1 部门树管理
- 支持手动创建、编辑部门
- 支持从IM平台同步组织架构

#### 4.6.2 IM组织架构同步
- **手动同步**：`POST /api/organization/sync`
- **定时同步**：每天凌晨全量同步一次
- **同步策略**：匹配手机号或邮箱，关联本地用户；用户离职标记状态为禁用，不删除

### 4.7 通知模块

#### 4.7.1 多渠道通知
- **日志记录**（默认）：仅记录日志
- **钉钉工作消息**：推送至钉钉
- **飞书机器人消息**：推送至飞书
- **企业微信消息**：推送至企业微信

#### 4.7.2 通知节点
- 预定成功通知
- 审批结果通知
- 会前15分钟提醒
- 签到提醒
- 爽约扣分通知

### 4.8 多租户模块

#### 4.8.1 租户CRUD
- 超级管理员可创建、编辑、删除租户
- 支持设置租户订阅到期时间
- 创建租户时可同时创建管理员账号

#### 4.8.2 租户隔离
- 所有业务数据通过`tenant_id`字段关联
- 使用中间件`CheckTenant`确保用户只能访问所属租户的数据
- Pad页面通过`access_code`实现租户隔离

#### 4.8.3 租户IM配置
- 每个租户可独立配置钉钉/飞书/企业微信
- 配置项包括：AppId、AppSecret、CorpId、AgentId等
- 支持在租户管理员页面和超级管理员页面配置

---

## 五、数据库设计

### 5.1 核心表结构

#### 5.1.1 tenants（租户表）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键 |
| name | varchar(100) | 租户名称 |
| domain | varchar(100) | 租户域名 |
| contact_name | varchar(50) | 联系人 |
| contact_phone | varchar(20) | 联系电话 |
| contact_email | varchar(100) | 联系邮箱 |
| status | boolean | 状态（启用/禁用） |
| subscription_until | date | 订阅到期时间 |
| dingtalk_enabled | boolean | 是否启用钉钉 |
| dingtalk_app_key | varchar(100) | 钉钉AppKey |
| dingtalk_app_secret | varchar(100) | 钉钉AppSecret |
| dingtalk_corp_id | varchar(100) | 钉钉CorpId |
| dingtalk_agent_id | varchar(50) | 钉钉AgentId |
| dingtalk_process_code | varchar(100) | 钉钉审批流程Code |
| feishu_enabled | boolean | 是否启用飞书 |
| feishu_app_id | varchar(100) | 飞书AppId |
| feishu_app_secret | varchar(100) | 飞书AppSecret |
| feishu_verification_token | varchar(255) | 飞书Verification Token |
| feishu_app_type | varchar(20) | 飞书应用类型（self/store） |
| feishu_approval_code | varchar(100) | 飞书审批定义Code |
| wework_enabled | boolean | 是否启用企业微信 |
| wework_corp_id | varchar(100) | 企业微信CorpId |
| wework_secret | varchar(100) | 企业微信应用Secret |
| wework_agent_id | varchar(50) | 企业微信AgentId |
| wework_token | varchar(100) | 企业微信回调Token |
| wework_encoding_aes_key | varchar(100) | 企业微信回调EncodingAESKey |
| wework_approval_code | varchar(100) | 企业微信审批模板Code |
| im_notification_channel | varchar(20) | IM通知渠道（log/dingtalk/feishu/wework） |

#### 5.1.2 users（用户表）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键 |
| tenant_id | int | 租户ID（超级管理员为null） |
| name | varchar(100) | 用户姓名 |
| email | varchar(100) | 邮箱 |
| password | varchar(255) | 密码（加密） |
| phone | varchar(20) | 手机号 |
| department_id | int | 部门ID |
| position | varchar(50) | 职位 |
| dingtalk_user_id | varchar(100) | 钉钉用户ID |
| feishu_open_id | varchar(100) | 飞书用户ID |
| wework_user_id | varchar(100) | 企业微信用户ID |
| is_manager | boolean | 是否为部门经理 |
| is_admin | boolean | 是否为租户管理员 |
| is_super_admin | boolean | 是否为超级管理员 |
| credit_score | int | 信用分数（默认100） |
| avatar | varchar(255) | 头像URL |
| status | boolean | 状态（启用/禁用） |
| source | varchar(20) | 注册来源（LOCAL/DINGTALK/FEISHU/WEWORK） |

#### 5.1.3 meeting_rooms（会议室表）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键 |
| tenant_id | int | 租户ID |
| name | varchar(100) | 会议室名称 |
| floor | varchar(50) | 楼层 |
| capacity | int | 容纳人数 |
| location | varchar(200) | 位置描述 |
| equipment | json | 设备列表（投影仪、白板等） |
| status | boolean | 状态（启用/禁用） |
| access_code | varchar(20) | 访问码（8位随机字符串） |
| qr_code | varchar(255) | 二维码URL |

#### 5.1.4 reservations（预定表）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键 |
| tenant_id | int | 租户ID |
| user_id | int | 预定人ID |
| meeting_room_id | int | 会议室ID |
| title | varchar(200) | 会议主题 |
| description | text | 会议描述 |
| start_time | datetime | 开始时间 |
| end_time | datetime | 结束时间 |
| attendees | json | 参会人员列表 |
| status | varchar(20) | 状态（pending/approved/rejected/checked_in/completed） |
| checkin_time | datetime | 签到时间 |
| checkout_time | datetime | 签退时间 |
| needs_approval | boolean | 是否需要审批 |

#### 5.1.5 approvals（审批表）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键 |
| tenant_id | int | 租户ID |
| reservation_id | int | 预定ID |
| user_id | int | 审批人ID |
| process_instance_id | varchar(100) | IM审批实例ID |
| status | varchar(20) | 状态（pending/approved/rejected） |
| comment | text | 审批意见 |

### 5.2 表关系图

```
tenants ──1:N── users
tenants ──1:N── meeting_rooms
tenants ──1:N── reservations
tenants ──1:N── departments

users ──1:N── reservations
users ──1:N── approvals

meeting_rooms ──1:N── reservations

departments ──1:N── users

reservations ──1:1── approvals
```

---

## 六、IM集成架构

### 6.1 IMService接口设计

```php
interface IMService
{
    public function setTenant(int $tenantId): void;
    public function getTenantId(): ?int;
    public function getUserByAuthCode(string $code): array;
    public function syncOrganization(bool $fullSync = false): bool;
    public function sendApproval(array $booking): array;
    public function handleApprovalCallback(array $data): array;
    public function sendNotification(string $userId, string $message, string $type = 'text'): bool;
    public function getJsApiConfig(string $url): array;
    public function getDepartments(bool $withUsers = false): array;
    public function getUserInfo(string $userId): array;
    public function getAccessToken(): string;
    public function isEnabled(): bool;
}
```

### 6.2 服务实现类

| 服务类 | 说明 |
|--------|------|
| DingtalkService | 钉钉IM服务实现，调用钉钉开放平台API |
| FeishuService | 飞书IM服务实现，调用飞书开放平台API |
| WeworkService | 企业微信IM服务实现，调用企业微信开放平台API |

### 6.3 IMServiceFactory工厂模式

```php
class IMServiceFactory
{
    public static function createDingtalkService(int $tenantId): DingtalkService;
    public static function createFeishuService(int $tenantId): FeishuService;
    public static function createWeworkService(int $tenantId): WeworkService;
    public static function getEnabledService(int $tenantId): ?IMService;
    public static function getServiceBySource(int $tenantId, string $source): ?IMService;
    public static function getServiceByNotificationChannel(int $tenantId): ?IMService;
    public static function getNotificationChannel(int $tenantId): string;
}
```

### 6.4 回调接口设计

| IM平台 | 回调地址 | 处理方法 |
|--------|----------|----------|
| 钉钉 | `https://meeting.sisuu.com/api/callback/approval/dingtalk` | ApprovalCallbackController@dingtalkCallback |
| 飞书 | `https://meeting.sisuu.com/api/callback/approval/feishu` | ApprovalCallbackController@feishuCallback |
| 企业微信 | `https://meeting.sisuu.com/api/callback/approval/wework` | ApprovalCallbackController@weworkCallback |

---

## 七、部署与运行

### 7.1 环境要求

| 环境 | 要求 |
|------|------|
| PHP | 8.2+ |
| MySQL | 8.0+ |
| Node.js | 18+ |
| Nginx | 1.20+ |

### 7.2 后端部署步骤

```bash
# 1. 进入项目目录
cd /www/wwwroot/meeting.sisuu.com/backend

# 2. 安装依赖
composer install

# 3. 配置环境变量
cp .env.example .env
# 编辑 .env 文件，配置数据库连接等

# 4. 生成应用密钥
php artisan key:generate

# 5. 执行数据库迁移
php artisan migrate

# 6. 填充测试数据（可选）
php artisan db:seed

# 7. 启动开发服务器
php artisan serve --host=127.0.0.1 --port=8000

# 或使用php-fpm部署（生产环境）
```

### 7.3 前端构建与部署

```bash
# 1. 进入项目目录
cd /www/wwwroot/meeting.sisuu.com/frontend

# 2. 安装依赖
npm install

# 3. 构建生产版本
npm run build

# 4. 构建产物位于 dist/ 目录，Nginx配置指向该目录
```

### 7.4 Nginx配置说明

当前配置文件：`/www/server/panel/vhost/nginx/meeting.sisuu.com.conf`

```nginx
server {
    listen 80;
    server_name meeting.sisuu.com;
    index index.html;
    root /www/wwwroot/meeting.sisuu.com/frontend/dist;

    # API代理到后端
    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # 前端路由
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

### 7.5 数据库迁移

```bash
# 执行所有未执行的迁移
php artisan migrate

# 回滚最近一次迁移
php artisan migrate:rollback

# 创建新迁移文件
php artisan make:migration create_xxx_table
```

### 7.6 生产环境进程管理

#### 7.6.1 当前部署方式（临时）
当前生产环境使用 `php artisan serve` 启动后端服务，这种方式**仅适用于开发环境**，终端关闭后服务会停止。

```bash
# 当前启动命令（临时）
php artisan serve --host=127.0.0.1 --port=8000

# 建议使用nohup保持后台运行
nohup php artisan serve --host=127.0.0.1 --port=8000 > /dev/null 2>&1 &
```

#### 7.6.2 推荐生产环境部署方式（php-fpm）
通过宝塔面板配置php-fpm，确保服务稳定运行：

1. 登录宝塔面板
2. 进入网站管理 → meeting.sisuu.com → 设置 → 反向代理
3. 添加反向代理：
   - 代理名称：Laravel API
   - 目标URL：`http://127.0.0.1:8000`（或直接使用php-fpm）
4. 配置完成后重启Nginx

#### 7.6.3 使用Supervisor管理进程（推荐）

```bash
# 安装Supervisor
yum install supervisor

# 创建配置文件
cat > /etc/supervisor/conf.d/meeting-room.conf <<EOF
[program:meeting-room]
command=/www/server/php/82/bin/php /www/wwwroot/meeting.sisuu.com/backend/artisan serve --host=127.0.0.1 --port=8000
directory=/www/wwwroot/meeting.sisuu.com/backend
user=root
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/www/wwwlogs/meeting-room.log
EOF

# 启动Supervisor
systemctl start supervisord
systemctl enable supervisord

# 管理服务
supervisorctl start meeting-room
supervisorctl stop meeting-room
supervisorctl restart meeting-room
```

### 7.7 Laravel Scheduler定时任务

系统使用Laravel Scheduler实现定时任务，包括每天凌晨全量同步组织架构。

#### 7.7.1 配置Cron Job

```bash
# 编辑crontab
crontab -e

# 添加以下内容
* * * * * cd /www/wwwroot/meeting.sisuu.com/backend && /www/server/php/82/bin/php artisan schedule:run >> /dev/null 2>&1
```

#### 7.7.2 定时任务列表

| 任务 | 执行时间 | 说明 |
|------|----------|------|
| 组织架构全量同步 | 每天凌晨2:00 | 同步钉钉/飞书/企业微信组织架构 |

#### 7.7.3 手动执行定时任务

```bash
# 执行所有调度任务
php artisan schedule:run

# 手动触发组织架构同步
php artisan organization:sync
```

### 7.8 服务器环境配置

#### 7.8.1 宝塔面板管理
- **面板地址**：`http://服务器IP:8888`
- **管理内容**：网站配置、数据库管理、PHP版本切换、Nginx配置、SSL证书等

#### 7.8.2 PHP配置
- **版本**：PHP 8.2
- **安装路径**：`/www/server/php/82/bin/php`
- **配置文件**：`/www/server/php/82/etc/php.ini`
- **关键配置**：
  - `memory_limit = 512M`
  - `upload_max_filesize = 64M`
  - `post_max_size = 64M`

#### 7.8.3 目录结构
```
/www/wwwroot/meeting.sisuu.com/
├── backend/                    # 后端代码
│   ├── public/                 # 公开目录（Nginx可访问）
│   ├── storage/                # 存储目录（需写入权限）
│   └── .env                    # 环境配置
├── frontend/                   # 前端代码
│   ├── src/                    # 源代码
│   └── dist/                   # 构建产物（Nginx指向）
└── TECHNICAL_HANDOVER.md       # 技术移交文档
```

#### 7.8.4 日志文件
- **Nginx访问日志**：`/www/wwwlogs/meeting.sisuu.com.log`
- **Nginx错误日志**：`/www/wwwlogs/meeting.sisuu.com.error.log`
- **Laravel日志**：`/www/wwwroot/meeting.sisuu.com/backend/storage/logs/laravel.log`

---

## 八、开发流程

### 8.1 代码规范

- **前端**：遵循Vue官方风格指南，使用ESLint（如果配置）
- **后端**：遵循Laravel官方风格指南，使用PHP CS Fixer（如果配置）
- **命名规范**：类名使用驼峰命名，方法名使用小驼峰，变量名使用小驼峰

### 8.2 分支管理策略

建议使用Git Flow或GitHub Flow：
- `main`：生产分支
- `develop`：开发分支
- `feature/*`：功能分支
- `bugfix/*`：Bug修复分支

### 8.3 测试流程

- **后端测试**：使用PHPUnit编写单元测试和功能测试
- **前端测试**：使用Vitest编写组件测试
- **手动测试**：关键功能进行手动回归测试

### 8.4 部署流程

1. 在开发环境完成功能开发和测试
2. 合并到develop分支进行集成测试
3. 合并到main分支
4. 在生产服务器拉取最新代码
5. 执行数据库迁移（如有）
6. 重新构建前端（如有）
7. 重启服务

---

## 九、常见问题与解决方案

### 9.1 登录失败问题

**问题现象**：登录页面提示"登录失败"，控制台显示502 Bad Gateway

**可能原因**：
- 后端服务未启动
- PHP版本不匹配（需要8.2）
- 数据库连接失败
- storage目录权限不足

**解决方案**：
```bash
# 检查后端服务状态
ps aux | grep php

# 启动后端服务
php artisan serve --host=127.0.0.1 --port=8000

# 检查PHP版本
php -v

# 修复storage权限
chmod -R 777 /www/wwwroot/meeting.sisuu.com/backend/storage

# 检查数据库连接
php artisan migrate:status
```

### 9.2 IM配置问题

**问题现象**：IM配置保存后不生效

**可能原因**：
- 数据库缺少IM配置字段（未执行迁移）
- Tenant模型$fillable未包含新字段
- 前端表单未提交新字段

**解决方案**：
```bash
# 执行数据库迁移
php artisan migrate
```

### 9.3 审批回调问题

**问题现象**：IM平台审批后，系统状态未更新

**可能原因**：
- 回调地址配置错误
- 回调请求未携带tenant_id参数
- IM平台的Token验证失败

**解决方案**：
- 确认回调地址为：`https://meeting.sisuu.com/api/callback/approval/{platform}`
- 检查IM平台配置的回调URL是否正确
- 查看日志文件：`/www/wwwlogs/meeting.sisuu.com.error.log`

### 9.4 数据库迁移问题

**问题现象**：执行migrate时报错

**可能原因**：
- 数据库连接失败
- 表已存在
- 字段类型不兼容

**解决方案**：
- 检查.env文件中的数据库配置
- 使用`--force`参数强制执行
- 查看具体错误信息

---

## 十、待开发功能清单

| 优先级 | 功能 | 说明 |
|--------|------|------|
| 高 | IM机器人交互 | 钉钉/飞书/企业微信群机器人支持指令查询 |
| 高 | 移动端H5优化 | 优化移动端展示和交互体验 |
| 中 | 消息模板管理 | 支持自定义通知消息模板 |
| 中 | 审批超时催办 | 审批超时自动发送催办消息 |
| 中 | 会议室使用统计 | 更详细的会议室使用数据分析 |
| 低 | 邮件通知 | 支持邮件通知渠道 |
| 低 | API开放平台 | 提供第三方集成API |

---

## 附录

### A. API接口清单（部分）

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/login` | POST | 用户登录 |
| `/api/logout` | POST | 用户登出 |
| `/api/me` | GET | 获取当前用户信息 |
| `/api/meeting-rooms` | GET/POST | 会议室列表/创建 |
| `/api/meeting-rooms/{id}` | GET/PUT/DELETE | 会议室详情/更新/删除 |
| `/api/reservations` | GET/POST | 预定列表/创建 |
| `/api/reservations/{id}` | GET/PUT/DELETE | 预定详情/更新/删除 |
| `/api/reservations/{id}/checkin` | POST | 签到 |
| `/api/approvals` | GET | 审批列表 |
| `/api/approvals/{id}/approve` | POST | 审批通过 |
| `/api/approvals/{id}/reject` | POST | 审批拒绝 |
| `/api/organization/sync` | POST | 同步组织架构 |
| `/api/tenants/im-config` | GET/PUT | 获取/更新IM配置 |
| `/api/booking-policy` | GET/PUT | 获取/更新预定规则（PUT 需 admin） |
| `/api/reservations/calendar` | GET | 日历视图数据（start_date, end_date, meeting_room_id, my_only） |
| `/api/reservations/{id}/reschedule` | PATCH | 拖拽改期（start_time, end_time） |
| `/api/callback/bot/dingtalk?tenant_id=` | POST | 钉钉机器人指令回调 |
| `/api/callback/bot/feishu?tenant_id=` | POST | 飞书机器人指令回调 |
| `/api/message-templates` | GET | 消息模板列表（admin，首次访问自动初始化默认模板） |
| `/api/message-templates/{id}` | PUT | 更新模板 |
| `/api/message-templates/preview` | POST | 预览模板 |
| `/api/message-templates/{id}/reset` | POST | 重置为默认 |
| `/api/room-blackouts` | GET/POST | 维护时段列表/创建（admin） |
| `/api/room-blackouts/{id}` | PUT/DELETE | 更新/删除维护时段 |
| `/api/reports/heatmap` | GET | 预定热力图（hour × weekday） |
| `/api/reports/utilization/export` | GET | 利用率 CSV 导出 |
| `/api/reservations` POST 支持 | | `booked_for_user_id` 代预定（admin/同部门 manager） |
| `/api/meeting-rooms/recommend` | POST | 智能推荐 Top3 会议室 |
| `/api/waitlist` | GET/POST | 候补队列 |
| `/api/waitlist/{id}/confirm` | POST | 确认候补转预定 |
| `/api/credit/logs` | GET | 信用分明细 |
| `/api/calendar/status` | GET | 日历同步状态 + ICS 订阅 URL |
| `/api/calendar/feed/{token}.ics` | GET | 个人 ICS 日历订阅（无需登录） |
| `/api/webhook-endpoints` | CRUD | Webhook 配置（admin） |
| `/api/api-tokens` | GET/POST/DELETE | 开放 API Token（admin） |
| `/api/open/v1/*` | | 开放 API（Bearer Token 认证） |
| `/api/admin/tenants` | GET/POST | 租户列表/创建（超级管理员） |
| `/api/admin/tenants/{id}` | GET/PUT/DELETE | 租户详情/更新/删除（超级管理员） |

### B. 测试账号

| 角色 | 邮箱 | 密码 |
|------|------|------|
| 超级管理员 | super@admin.com | 123456 |
| 租户1管理员 | admin@demo.com | 123456 |
| 租户2管理员 | admin@inno.com | 123456 |
| 租户1用户 | zhangsan@demo.com | 123456 |

### C. 系统日志

- **访问日志**：`/www/wwwlogs/meeting.sisuu.com.log`
- **错误日志**：`/www/wwwlogs/meeting.sisuu.com.error.log`
- **Laravel日志**：`/www/wwwroot/meeting.sisuu.com/backend/storage/logs/laravel.log`

### D. .env环境配置示例

```env
# 基础配置
APP_NAME=MeetingRoom
APP_ENV=production
APP_KEY=base64:your-key-here
APP_DEBUG=false
APP_URL=http://meeting.sisuu.com/

# 数据库配置
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=meeting_room
DB_USERNAME=meeting_room
DB_PASSWORD=your-password-here

# 缓存配置
BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

# 钉钉配置（全局默认，租户可覆盖）
DINGTALK_ENABLED=false
DINGTALK_APP_KEY=
DINGTALK_APP_SECRET=
DINGTALK_CORP_ID=
DINGTALK_AGENT_ID=
DINGTALK_CALLBACK_TOKEN=
DINGTALK_CALLBACK_AES_KEY=

# 飞书配置（全局默认，租户可覆盖）
FEISHU_ENABLED=false
FEISHU_APP_ID=
FEISHU_APP_SECRET=
FEISHU_VERIFICATION_TOKEN=
FEISHU_ENCRYPT_KEY=

# 企业微信配置（全局默认，租户可覆盖）
WEWORK_ENABLED=false
WEWORK_CORP_ID=
WEWORK_SECRET=
WEWORK_AGENT_ID=
WEWORK_TOKEN=
WEWORK_ENCODING_AES_KEY=

# IM通知渠道配置 (log/dingtalk/feishu/wework)
IM_NOTIFICATION_CHANNEL=log
```

### E. 数据库迁移文件说明

| 迁移文件 | 说明 |
|----------|------|
| `2024_01_01_000001_create_departments_table.php` | 创建部门表 |
| `2024_01_01_000002_create_users_table.php` | 创建用户表 |
| `2024_01_01_000004_create_meeting_rooms_table.php` | 创建会议室表 |
| `2024_01_01_000005_create_reservations_table.php` | 创建预定表 |
| `2024_01_01_000007_create_approvals_table.php` | 创建审批表 |
| `2024_01_01_000010_create_tenants_table.php` | 创建租户表 |
| `2024_01_01_000011_add_tenant_id_to_tables.php` | 为各表添加tenant_id字段 |
| `2024_01_01_000014_add_im_config_to_tenants.php` | 为租户添加钉钉/飞书配置字段 |
| `2024_01_01_000015_add_wework_config_to_tenants.php` | 为租户添加企业微信配置字段 |
| `2024_01_01_000015_add_access_code_to_meeting_rooms.php` | 为会议室添加access_code字段 |
| `2024_01_01_000016_add_feishu_extra_fields.php` | 为租户添加飞书Verification Token和App Type字段 |
| `2024_01_01_000017_add_approval_and_org_fields.php` | 审批实例ID、部门IM ID、钉钉回调字段 |
| `2024_01_01_000018_add_composite_uniques.php` | 复合唯一索引、device_tags.tenant_id |
| `2024_01_01_000019_create_jobs_table.php` | 队列 jobs / failed_jobs 表 |
| `2024_01_01_000020_add_booking_policy_fields.php` | 租户预定规则、remind_sent、审批催办字段 |
| `2024_01_01_000021_add_phase4_features.php` | message_templates、room_blackouts、booked_by_user_id |
| `2024_01_01_000022_add_phase5_features.php` | 信用/候补/Webhook/API Token/日历同步 |

---

### H. Phase 4 功能说明

| 功能 | 说明 |
|------|------|
| 消息模板 | 管理员可编辑 8 类通知模板，变量 `{title}` `{room_name}` 等；NotificationService 自动渲染 |
| 代预定 | admin 可为全租户用户代订；manager 可为同部门用户代订；审批走被代订人部门 |
| 维护时段 | 管理员标记会议室不可预定时段，与长期禁用 status 区分；预定/改期自动校验 |
| 报表热力图 | 8–21 点 × 周一至周日预定次数矩阵 |
| H5 移动端 | 宽度 ≤768px 隐藏侧栏，底部 Tab：首页/日历/预定/我的/审批 |

### K. Phase 5 功能说明

| 功能 | 说明 |
|------|------|
| 日历同步 | 个人 ICS 订阅链接；可选 Google/Outlook OAuth 双向推送（需配置 CLIENT_ID） |
| 智能推荐 | 按收藏、历史、容量匹配推荐 Top3 空闲会议室 |
| 候补队列 | 时段已满可排队；释放/取消后通知，限时确认转预定 |
| 开放 API | `/api/open/v1/`，租户 API Token 认证 |
| Webhook | 预定创建/取消/签到/爽约事件 HTTP 推送，HMAC 签名 |
| 信用体系 | 爽约扣分、签到加分、低于阈值限制预定，明细可查 |

**开放 API 示例：**
```bash
curl -H "Authorization: Bearer {prefix}.{secret}" \
  https://meeting.sisuu.com/api/open/v1/meeting-rooms
```

**日历 OAuth 环境变量（可选）：**
```env
GOOGLE_CALENDAR_CLIENT_ID=
GOOGLE_CALENDAR_CLIENT_SECRET=
MICROSOFT_CALENDAR_CLIENT_ID=
MICROSOFT_CALENDAR_CLIENT_SECRET=
```

**定时任务：** `waitlist:expire` 每分钟处理过期候补

---

### I. Phase 3 定时任务

| 命令 | 调度 | 说明 |
|------|------|------|
| `reservations:release-no-show` | 每 5 分钟 | 未签到超时自动释放会议室 |
| `reservations:send-reminders` | 每分钟 | 会前提醒（按租户 meeting_remind_minutes） |
| `approvals:remind-pending` | 每小时 | 待审批催办（按 approval_remind_hours / max_reminds） |

需确保 `php artisan schedule:run` 已加入 crontab，且 `QUEUE_CONNECTION=database` 时 Supervisor 已启动 queue worker。

### J. IM 机器人指令（钉钉/飞书）

用户在 IM 中 @机器人 可发送：

- `帮助` / `help` — 指令说明
- `查空闲 会议室名 14:00-15:00` — 查询空闲
- `今日会议` — 查看今日预定

回调地址：`POST /api/callback/bot/dingtalk?tenant_id={租户ID}` 或 `/api/callback/bot/feishu?tenant_id={租户ID}`

---

**文档版本**：v1.4  
**编写日期**：2026-07-07  
**编写人**：系统开发团队