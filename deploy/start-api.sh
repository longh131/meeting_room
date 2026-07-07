#!/bin/bash
# 临时启动 API（Supervisor 未安装时使用）
cd /www/wwwroot/meeting.sisuu.com/backend
if ! pgrep -f "artisan serve --host=127.0.0.1 --port=8000" >/dev/null; then
  nohup /www/server/php/82/bin/php artisan serve --host=127.0.0.1 --port=8000 >> /www/wwwlogs/meeting-room-api.log 2>&1 &
fi
if ! pgrep -f "queue:work database" >/dev/null; then
  nohup /www/server/php/82/bin/php artisan queue:work database --sleep=3 --tries=3 >> /www/wwwlogs/meeting-room-queue.log 2>&1 &
fi
