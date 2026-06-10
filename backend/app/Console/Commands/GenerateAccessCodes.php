<?php

namespace App\Console\Commands;

use App\Models\MeetingRoom;
use Illuminate\Console\Command;

class GenerateAccessCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meeting-room:generate-access-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '为所有未设置access_code的会议室生成随机访问码';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rooms = MeetingRoom::whereNull('access_code')->get();
        
        if ($rooms->isEmpty()) {
            $this->info('所有会议室都已设置access_code');
            return;
        }

        $this->info("开始为 {$rooms->count()} 个会议室生成access_code...");

        $count = 0;
        foreach ($rooms as $room) {
            $room->access_code = $this->generateAccessCode();
            $room->save();
            $count++;
            
            if ($count % 10 == 0) {
                $this->info("已处理 {$count} 个会议室");
            }
        }

        $this->info("成功为 {$count} 个会议室生成access_code");
    }

    /**
     * 生成8位随机访问码（大小写字母+数字）
     */
    private function generateAccessCode()
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $code = '';
        $max = strlen($characters) - 1;
        
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[random_int(0, $max)];
            }
        } while (MeetingRoom::where('access_code', $code)->exists());
        
        return $code;
    }
}
