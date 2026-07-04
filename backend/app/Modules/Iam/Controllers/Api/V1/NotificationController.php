<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Iam\Models\IamNotification;
use App\Modules\Iam\Resources\IamNotificationResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $actor = auth('api')->user();
        $items = IamNotification::query()
            ->where('user_id', $actor->id)
            ->orderByDesc('created_at')
            ->paginate(25);

        return ApiResponse::success(IamNotificationResource::collection($items));
    }

    public function unreadCount(): JsonResponse
    {
        $actor = auth('api')->user();
        $count = IamNotification::query()
            ->where('user_id', $actor->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success(['count' => $count]);
    }

    public function markRead(int $id): JsonResponse
    {
        $actor = auth('api')->user();
        $notification = IamNotification::query()
            ->where('user_id', $actor->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return ApiResponse::success(new IamNotificationResource($notification));
    }
}