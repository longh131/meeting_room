<?php

namespace App\Services;

use App\Models\Favorite;
use App\Models\MeetingRoom;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;

class RoomRecommendationService
{
    public function recommend(User $user, Carbon $startTime, Carbon $endTime, array $options = []): array
    {
        $capacity = (int) ($options['capacity'] ?? 0);
        $deviceTags = $options['device_tags'] ?? [];
        $floor = $options['floor'] ?? null;

        $query = MeetingRoom::where('status', 1);
        if ($capacity > 0) {
            $query->where('capacity', '>=', $capacity);
        }
        if ($floor) {
            $query->where('floor', $floor);
        }

        $rooms = $query->get();
        $favoriteIds = Favorite::where('user_id', $user->id)->pluck('meeting_room_id')->toArray();

        $historyCounts = Reservation::where('user_id', $user->id)
            ->whereIn('status', [Reservation::STATUS_RESERVED, Reservation::STATUS_CHECKIN, Reservation::STATUS_ENDED])
            ->selectRaw('meeting_room_id, count(*) as cnt')
            ->groupBy('meeting_room_id')
            ->pluck('cnt', 'meeting_room_id');

        $scored = [];
        foreach ($rooms as $room) {
            if (!$room->isAvailable($startTime, $endTime)) {
                continue;
            }

            if (!empty($deviceTags)) {
                $roomTags = $room->device_tags ?? [];
                if (count(array_intersect($deviceTags, $roomTags)) < count($deviceTags)) {
                    continue;
                }
            }

            $score = 0;
            if (in_array($room->id, $favoriteIds)) {
                $score += 30;
            }
            $score += min(20, (int) ($historyCounts[$room->id] ?? 0) * 2);
            if ($capacity > 0) {
                $score += max(0, 15 - abs($room->capacity - $capacity));
            }

            $scored[] = [
                'room' => $room,
                'score' => $score,
                'reasons' => $this->buildReasons($room, $favoriteIds, $historyCounts, $capacity),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_map(fn ($item) => [
            'room' => $item['room'],
            'score' => $item['score'],
            'reasons' => $item['reasons'],
        ], $scored), 0, 3);
    }

    protected function buildReasons(MeetingRoom $room, array $favoriteIds, $historyCounts, int $capacity): array
    {
        $reasons = [];
        if (in_array($room->id, $favoriteIds)) {
            $reasons[] = '常用收藏';
        }
        if (($historyCounts[$room->id] ?? 0) > 0) {
            $reasons[] = '历史常用';
        }
        if ($capacity > 0 && $room->capacity >= $capacity) {
            $reasons[] = "容量合适({$room->capacity}人)";
        }
        if (empty($reasons)) {
            $reasons[] = '当前时段空闲';
        }

        return $reasons;
    }
}
