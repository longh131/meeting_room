<?php

namespace App\Providers;

use App\Services\IM\DingtalkService;
use App\Services\IM\FeishuService;
use App\Services\IM\IMService;
use Illuminate\Support\ServiceProvider;

class IMServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(IMService::class, function ($app) {
            $dingtalkEnabled = env('DINGTALK_ENABLED', false);
            $feishuEnabled = env('FEISHU_ENABLED', false);

            if ($dingtalkEnabled) {
                return new DingtalkService();
            } elseif ($feishuEnabled) {
                return new FeishuService();
            }

            throw new \Exception('请配置IM服务（钉钉或飞书）');
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}