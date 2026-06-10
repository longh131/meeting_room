<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use Illuminate\Http\Request;

class MeetingRoomController extends Controller
{
    public function index(Request $request)
    {
        $query = MeetingRoom::where('status', 1);

        if ($request->has('floor')) {
            $query->where('floor', $request->floor);
        }

        if ($request->has('capacity')) {
            $query->where('capacity', '>=', $request->capacity);
        }

        if ($request->has('keyword')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->keyword . '%')
                  ->orWhere('code', 'like', '%' . $request->keyword . '%');
            });
        }

        $rooms = $query->withCount('reservations')->orderBy('sort_order')->paginate($request->per_page ?? 10);

        $rooms->getCollection()->transform(function ($room) {
            $tagIds = $room->device_tags ?? [];
            if (is_array($tagIds) && !empty($tagIds)) {
                $tags = \App\Models\DeviceTag::whereIn('id', $tagIds)->get(['id', 'name']);
                $room->device_tags = $tags->toArray();
            } else {
                $room->device_tags = [];
            }
            return $room;
        });

        return response()->json([
            'data' => $rooms->items(),
            'total' => $rooms->total(),
        ]);
    }

    public function show($id)
    {
        $room = MeetingRoom::with(['reservations' => function ($q) {
            $q->where('status', '!=', 5)->where('end_time', '>', now());
        }])->findOrFail($id);

        return response()->json($room);
    }

    public function store(Request $request)
    {
        $this->authorize('admin');

        $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:30|unique:meeting_rooms',
            'floor' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'device_tags' => 'nullable|array',
        ]);

        $room = MeetingRoom::create([
            'name' => $request->name,
            'code' => $request->code,
            'floor' => $request->floor,
            'capacity' => $request->capacity,
            'description' => $request->description,
            'device_tags' => $request->device_tags ?? [],
            'photo' => $request->photo,
            'floor_plan' => $request->floor_plan,
            'hourly_rate' => $request->hourly_rate,
            'status' => 1,
            'access_code' => $this->generateAccessCode(),
        ]);

        return response()->json($room, 201);
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

    public function update(Request $request, $id)
    {
        $this->authorize('admin');

        $room = MeetingRoom::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:30|unique:meeting_rooms,code,' . $id,
            'floor' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'device_tags' => 'nullable|array',
        ]);

        $room->update([
            'name' => $request->name,
            'code' => $request->code,
            'floor' => $request->floor,
            'capacity' => $request->capacity,
            'description' => $request->description,
            'device_tags' => $request->device_tags ?? [],
            'photo' => $request->photo,
            'floor_plan' => $request->floor_plan,
            'hourly_rate' => $request->hourly_rate,
            'status' => $request->status,
        ]);

        return response()->json($room);
    }

    public function destroy($id)
    {
        $this->authorize('admin');

        $room = MeetingRoom::findOrFail($id);
        $room->delete();

        return response()->json(['message' => '删除成功']);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'meeting_room_id' => 'required|integer',
            'start_time' => 'required|datetime',
            'end_time' => 'required|datetime|after:start_time',
            'exclude_reservation_id' => 'nullable|integer',
        ]);

        $room = MeetingRoom::findOrFail($request->meeting_room_id);
        $available = $room->isAvailable($request->start_time, $request->end_time, $request->exclude_reservation_id);

        return response()->json(['available' => $available]);
    }

    public function getFloors()
    {
        $floors = MeetingRoom::where('status', 1)->distinct()->pluck('floor')->sort();
        return response()->json($floors);
    }

    /**
     * 获取会议室PAD显示信息（当前状态、二维码等）
     * 通过access_code访问，确保租户隔离
     */
    public function padDisplay($accessCode)
    {
        $room = MeetingRoom::where('access_code', $accessCode)->firstOrFail();
        
        // 获取当前和近期的预定
        $now = now();
        $currentReservation = $room->reservations()
            ->whereNotIn('status', [4, 5]) // 排除已取消和爽约的
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now)
            ->with('user', 'attendees')
            ->first();
            
        $nextReservation = $room->reservations()
            ->whereNotIn('status', [4, 5])
            ->where('start_time', '>', $now)
            ->orderBy('start_time')
            ->with('user', 'attendees')
            ->first();
            
        // 生成二维码内容（签到页面地址），使用access_code确保租户隔离
        $qrCodeContent = url("/checkin/{$room->access_code}");
        
        // 计算会议室状态：0=空闲，1=使用中，2=即将使用（30分钟内）
        $status = 0;
        $statusText = '空闲';
        if ($currentReservation) {
            $status = 1;
            $statusText = '使用中';
        } elseif ($nextReservation && $nextReservation->start_time->diffInMinutes($now, false) <= 30) {
            $status = 2;
            $statusText = '即将使用';
        }
        
        return response()->json([
            'room' => $room,
            'status' => $status,
            'status_text' => $statusText,
            'current_time' => $now->format('Y-m-d H:i:s'),
            'current_reservation' => $currentReservation,
            'next_reservation' => $nextReservation,
            'qr_code_content' => $qrCodeContent,
            'today_reservations' => $room->reservations()
                ->whereNotIn('status', [4, 5])
                ->whereDate('start_time', $now->toDateString())
                ->with('user')
                ->orderBy('start_time')
                ->get(),
        ]);
    }

    /**
     * 通过会议室access_code签到，支持钉钉/飞书身份验证
     */
    public function padCheckin($accessCode)
    {
        $room = MeetingRoom::where('access_code', $accessCode)->firstOrFail();
        $tenant = $room->tenant;
        
        $now = now();
        $currentReservation = $room->reservations()
            ->whereNotIn('status', [4, 5])
            ->where('start_time', '<=', $now->copy()->addMinutes(30)) // 提前30分钟可签到
            ->where('end_time', '>', $now)
            ->with('attendees')
            ->first();
            
        if (!$currentReservation) {
            return response()->json(['message' => '当前无进行中的会议'], 400);
        }
        
        if ($currentReservation->checkin_time) {
            return response()->json(['message' => '会议已签到'], 400);
        }
        
        // 获取用户身份（支持钉钉/飞书OAuth或本地token）
        $user = $this->getUserFromRequest();
        
        if (!$user) {
            return response()->json(['message' => '无法识别用户身份'], 401);
        }
        
        // 验证用户是否是参会人员或组织者
        $isValidAttendee = false;
        
        // 检查是否是组织者
        if ($currentReservation->user_id == $user->id) {
            $isValidAttendee = true;
        }
        
        // 检查是否是参会人员
        if (!$isValidAttendee && $currentReservation->attendees) {
            foreach ($currentReservation->attendees as $attendee) {
                if ($attendee->user_id == $user->id) {
                    $isValidAttendee = true;
                    break;
                }
            }
        }
        
        if (!$isValidAttendee) {
            return response()->json(['message' => '您不是本次会议的参会人员'], 403);
        }
        
        // 签到
        $currentReservation->update([
            'checkin_time' => $now,
            'status' => 3, // 签到进行中
        ]);
        
        return response()->json([
            'message' => '签到成功',
            'reservation' => $currentReservation,
        ]);
    }
    
    /**
     * 从请求中获取用户身份
     * 支持：JWT token、钉钉OAuth、飞书OAuth
     */
    private function getUserFromRequest()
    {
        $token = request()->header('Authorization');
        
        // 尝试通过JWT token获取用户
        if ($token && str_starts_with($token, 'Bearer ')) {
            $jwtToken = substr($token, 7);
            try {
                $payload = \Firebase\JWT\JWT::decode(
                    $jwtToken,
                    new \Firebase\JWT\Key(config('app.key'), 'HS256')
                );
                return \App\Models\User::find($payload->sub);
            } catch (\Exception $e) {
                // JWT验证失败，继续尝试其他方式
            }
        }
        
        // 尝试通过钉钉临时授权码获取用户
        $dingtalkCode = request()->input('dingtalk_code');
        if ($dingtalkCode && request()->input('tenant_id')) {
            try {
                $tenant = \App\Models\Tenant::find(request()->input('tenant_id'));
                if ($tenant && $tenant->dingtalk_enabled) {
                    $imService = \App\Services\IM\IMServiceFactory::createDingtalkService($tenant->id);
                    $userInfo = $imService->getUserByAuthCode($dingtalkCode);
                    
                    // 查找或创建用户
                    return \App\Models\User::firstOrCreate(
                        ['dingtalk_user_id' => $userInfo['user_id']],
                        [
                            'name' => $userInfo['name'],
                            'email' => $userInfo['email'] ?? '',
                            'phone' => $userInfo['mobile'] ?? '',
                            'tenant_id' => $tenant->id,
                            'source' => 'DINGTALK',
                            'status' => true,
                        ]
                    );
                }
            } catch (\Exception $e) {
                // 钉钉验证失败
            }
        }
        
        // 尝试通过飞书临时授权码获取用户
        $feishuCode = request()->input('feishu_code');
        if ($feishuCode && request()->input('tenant_id')) {
            try {
                $tenant = \App\Models\Tenant::find(request()->input('tenant_id'));
                if ($tenant && $tenant->feishu_enabled) {
                    $imService = \App\Services\IM\IMServiceFactory::createFeishuService($tenant->id);
                    $userInfo = $imService->getUserByAuthCode($feishuCode);
                    
                    // 查找或创建用户
                    return \App\Models\User::firstOrCreate(
                        ['feishu_open_id' => $userInfo['open_id']],
                        [
                            'name' => $userInfo['name'],
                            'email' => $userInfo['email'] ?? '',
                            'phone' => $userInfo['mobile'] ?? '',
                            'tenant_id' => $tenant->id,
                            'source' => 'FEISHU',
                            'status' => true,
                        ]
                    );
                }
            } catch (\Exception $e) {
                // 飞书验证失败
            }
        }
        
        return null;
    }
}