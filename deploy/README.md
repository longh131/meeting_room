# 会议室预定系统 - 部署说明

## 队列（当前使用 database 驱动，无需 Redis）

```bash
# 1. 执行迁移（含 jobs / failed_jobs 表）
/www/server/php/82/bin/php artisan migrate --force

# 2. 修改 .env
QUEUE_CONNECTION=database

# 3. 使用 Supervisor 管理（推荐）
cp deploy/supervisor/meeting-room.conf /etc/supervisor/conf.d/
supervisorctl reread && supervisorctl update
supervisorctl start meeting-room-queue meeting-room-api
```

## 若后续安装 Redis

```env
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
```

Supervisor 中 `queue:work database` 改为 `queue:work redis`。

## 前端构建

```bash
cd frontend
NODE_OPTIONS=--max-old-space-size=768 npm run build
```

## 定时任务

```cron
* * * * * cd /www/wwwroot/meeting.sisuu.com/backend && /www/server/php/82/bin/php artisan schedule:run >> /dev/null 2>&1
```
