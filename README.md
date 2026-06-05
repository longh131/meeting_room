# 会议室预订系统

一个基于 Vue3 + Laravel 的多租户会议室预订系统。

## 功能特性

### 超级管理员功能
- 总控制台 - 查看系统概览统计
- 租户管理 - 创建、编辑、停用、启用租户
- 租户续费 - 管理租户订阅期限
- 系统报表 - 查看各租户使用统计

### 租户管理员功能
- 会议室管理 - 创建、编辑、删除会议室
- 用户管理 - 管理租户下的用户
- 预订管理 - 查看和审批预订请求
- 设备管理 - 管理会议室设备

### 用户功能
- 会议室预订 - 预订可用会议室
- 预订管理 - 查看和取消自己的预订
- 个人资料 - 查看和修改个人信息

## 技术栈

### 前端
- Vue 3.4+
- Element Plus
- Vue Router
- Pinia
- Vite

### 后端
- Laravel 11+
- MySQL 8.0+
- JWT Authentication

## 安装指南

### 环境要求
- PHP >= 8.2
- Node.js >= 18.x
- MySQL >= 8.0
- Composer

### 1. 克隆项目

```bash
git clone <repository-url>
cd meeting.sisuu.com
```

### 2. 后端配置

```bash
cd backend

# 安装依赖
composer install

# 复制配置文件
cp .env.example .env

# 编辑配置文件（修改数据库连接信息）
nano .env

# 生成应用密钥
php artisan key:generate

# 运行数据库迁移
php artisan migrate

# 运行数据库种子（创建测试数据）
php artisan db:seed

# 启动开发服务器
php artisan serve --host=0.0.0.0 --port=8000
```

### 3. 前端配置

```bash
cd frontend

# 安装依赖
npm install

# 开发模式运行
npm run dev

# 生产环境编译
npm run build
```

## 配置说明

### 后端环境变量 (.env)

```env
APP_NAME=会议室预订系统
APP_ENV=production
APP_KEY=your-app-key
APP_DEBUG=false
APP_URL=http://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=meeting_room
DB_USERNAME=your_username
DB_PASSWORD=your_password

JWT_SECRET=your-jwt-secret
```

### 前端配置

前端配置文件位于 `frontend/src/utils/axios.js`：

```javascript
const instance = axios.create({
  baseURL: '/api',  // 后端 API 地址
  timeout: 10000,
})
```

## 测试账号

### 超级管理员
- 邮箱: `super@admin.com`
- 密码: `123456`

### 租户1管理员
- 邮箱: `admin@demo.com`
- 密码: `123456`

### 租户2管理员
- 邮箱: `admin@inno.com`
- 密码: `123456`

### 租户1普通用户
- 邮箱: `zhangsan@demo.com`
- 密码: `123456`

## 项目结构

```
meeting.sisuu.com/
├── backend/                    # Laravel 后端
│   ├── app/
│   │   ├── Controllers/        # 控制器
│   │   ├── Models/             # 模型
│   │   ├── Middleware/         # 中间件
│   │   └── Providers/          # 服务提供者
│   ├── config/                 # 配置文件
│   ├── database/
│   │   ├── migrations/         # 数据库迁移
│   │   └── seeds/              # 数据库种子
│   ├── routes/                 # 路由定义
│   └── public/                 # 静态资源
└── frontend/                   # Vue3 前端
    ├── src/
    │   ├── components/         # 组件
    │   ├── views/              # 页面视图
    │   │   ├── super-admin/    # 超级管理员页面
    │   │   └── tenant/         # 租户页面
    │   ├── router/             # 路由配置
    │   ├── store/              # Pinia 状态管理
    │   ├── utils/              # 工具函数
    │   └── styles/             # 全局样式
    ├── index.html
    ├── package.json
    └── vite.config.js
```

## API 接口

### 超级管理员接口

| 方法 | 路径 | 描述 |
|------|------|------|
| GET | /api/admin/reports/overview | 获取系统概览统计 |
| GET | /api/admin/tenants | 获取租户列表 |
| POST | /api/admin/tenants | 创建租户 |
| GET | /api/admin/tenants/{id} | 获取租户详情 |
| PUT | /api/admin/tenants/{id} | 更新租户信息 |
| DELETE | /api/admin/tenants/{id} | 删除租户 |
| POST | /api/admin/tenants/{id}/renew | 租户续费 |

### 认证接口

| 方法 | 路径 | 描述 |
|------|------|------|
| POST | /api/login | 用户登录 |
| POST | /api/logout | 用户登出 |
| GET | /api/me | 获取当前用户信息 |

## 部署指南

### 使用 Nginx

创建 Nginx 配置文件 `/etc/nginx/sites-available/meeting.sisuu.com.conf`：

```nginx
server {
    listen 80;
    server_name meeting.sisuu.com;

    # 前端静态文件
    root /path/to/meeting.sisuu.com/frontend/dist;
    index index.html;

    # 前端路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API 代理
    location /api/ {
        proxy_pass http://localhost:8000/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

启用配置并重启 Nginx：

```bash
ln -s /etc/nginx/sites-available/meeting.sisuu.com.conf /etc/nginx/sites-enabled/
systemctl restart nginx
```

### 使用 Supervisor 管理后端进程

创建 Supervisor 配置文件 `/etc/supervisor/conf.d/meeting-room.conf`：

```ini
[program:meeting-room]
command=php /path/to/meeting.sisuu.com/backend/artisan serve --host=0.0.0.0 --port=8000
directory=/path/to/meeting.sisuu.com/backend
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/meeting-room.err.log
stdout_logfile=/var/log/meeting-room.out.log
```

启动 Supervisor：

```bash
supervisorctl reread
supervisorctl update
supervisorctl start meeting-room
```

## 定时任务

系统包含自动停用过期租户的定时任务：

```bash
# 查看定时任务列表
php artisan schedule:list

# 运行定时任务
php artisan schedule:run
```

推荐配置 Cron 定时执行：

```bash
* * * * * cd /path/to/meeting.sisuu.com/backend && php artisan schedule:run >> /dev/null 2>&1
```

## 常见问题

### 前端页面空白
- 检查前端是否已编译：`npm run build`
- 检查 Nginx 配置是否正确指向 `dist` 目录
- 清除浏览器缓存或使用无痕模式

### 数据库连接失败
- 检查 `.env` 文件中的数据库配置
- 确认数据库服务已启动：`systemctl start mysqld`
- 确认数据库用户有正确的权限

### 登录失败
- 确认后端服务正在运行
- 检查 API 请求路径是否正确
- 检查 JWT 密钥配置

## 开发规范

### 代码风格
- 前端使用 ESLint 进行代码检查
- 后端遵循 PSR-12 代码规范

### 提交规范
- feat: 新增功能
- fix: 修复 bug
- docs: 更新文档
- style: 代码格式调整
- refactor: 代码重构
- test: 添加测试

## 许可证

MIT License

## 联系方式

如有问题，请联系开发团队。
