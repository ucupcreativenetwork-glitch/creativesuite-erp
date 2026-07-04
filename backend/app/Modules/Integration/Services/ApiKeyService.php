<?php

namespace App\Modules\Integration\Services;

use App\Modules\Core\Models\User;
use App\Modules\Integration\Models\IntegrationApiKey;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Str;

class ApiKeyService
{
    use ChecksPermissions;

    public function list(User $user)
    {
        $this->assertPermission($user, 'int.api_key.read');

        return IntegrationApiKey::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(User $user, array $data): array
    {
        $this->assertPermission($user, 'int.api_key.manage');

        $scopes = $data['scopes'] ?? [];
        $available = config('integration.available_scopes', []);
        foreach ($scopes as $scope) {
            if (! in_array($scope, $available, true)) {
                throw new ApiException("Invalid scope: {$scope}", 422, 'INVALID_SCOPE');
            }
        }

        $prefix = config('integration.api_key_prefix', 'cs_live_');
        $secret = Str::random(40);
        $rawKey = $prefix.$secret;
        $keyPrefix = substr($rawKey, 0, 16);

        $apiKey = IntegrationApiKey::query()->create([
            'public_id' => (string) Str::uuid(),
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'name' => $data['name'],
            'key_prefix' => $keyPrefix,
            'key_hash' => hash('sha256', $rawKey),
            'scopes' => $scopes,
            'is_active' => true,
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $user->id,
        ]);

        return [
            'api_key' => $apiKey,
            'plain_text_key' => $rawKey,
        ];
    }

    public function revoke(User $user, string $publicId): IntegrationApiKey
    {
        $this->assertPermission($user, 'int.api_key.manage');

        $apiKey = $this->findScoped($user, $publicId);
        $apiKey->update(['is_active' => false]);

        return $apiKey->fresh();
    }

    protected function findScoped(User $user, string $publicId): IntegrationApiKey
    {
        return IntegrationApiKey::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}