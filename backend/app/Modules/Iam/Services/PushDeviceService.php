<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\User;
use App\Modules\Iam\Models\IamPushDevice;
use Illuminate\Support\Collection;

class PushDeviceService
{
    public function register(User $user, string $expoPushToken, ?string $platform = null): IamPushDevice
    {
        $device = IamPushDevice::query()
            ->withoutGlobalScope('tenant')
            ->where('expo_push_token', $expoPushToken)
            ->first();

        if ($device) {
            $device->update([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'platform' => $platform ?? $device->platform,
                'last_used_at' => now(),
            ]);

            return $device->fresh();
        }

        return IamPushDevice::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'expo_push_token' => $expoPushToken,
            'platform' => $platform,
            'last_used_at' => now(),
        ]);
    }

    public function unregister(User $user, string $expoPushToken): bool
    {
        return IamPushDevice::query()
            ->where('user_id', $user->id)
            ->where('expo_push_token', $expoPushToken)
            ->delete() > 0;
    }

    public function unregisterAllForUser(User $user): int
    {
        return IamPushDevice::query()
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * @return Collection<int, string>
     */
    public function tokensForUser(int $userId): Collection
    {
        return IamPushDevice::query()
            ->where('user_id', $userId)
            ->pluck('expo_push_token');
    }

    public function removeStaleTokens(array $tokens): int
    {
        if ($tokens === []) {
            return 0;
        }

        return IamPushDevice::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('expo_push_token', $tokens)
            ->delete();
    }
}