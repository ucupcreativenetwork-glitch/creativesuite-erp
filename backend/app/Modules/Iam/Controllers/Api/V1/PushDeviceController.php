<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Iam\Services\PushDeviceService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PushDeviceController extends Controller
{
    public function __construct(protected PushDeviceService $devices) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expo_push_token' => ['required', 'string', 'max:255', 'regex:/^(Exponent|Expo)PushToken\[/'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ]);

        $actor = auth('api')->user();
        $device = $this->devices->register(
            $actor,
            $data['expo_push_token'],
            $data['platform'] ?? null,
        );

        return ApiResponse::success([
            'id' => $device->id,
            'platform' => $device->platform,
            'registered_at' => $device->last_used_at?->toIso8601String(),
        ]);
    }

    public function unregister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expo_push_token' => ['required', 'string', 'max:255'],
        ]);

        $actor = auth('api')->user();
        $removed = $this->devices->unregister($actor, $data['expo_push_token']);

        return ApiResponse::success(['removed' => $removed]);
    }
}