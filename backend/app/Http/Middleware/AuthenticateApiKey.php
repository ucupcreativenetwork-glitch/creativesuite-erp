<?php

namespace App\Http\Middleware;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Integration\Models\IntegrationApiKey;
use App\Support\Exceptions\ApiException;
use App\Support\Tenant\CompanyManager;
use App\Support\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(
        protected TenantManager $tenantManager,
        protected CompanyManager $companyManager,
    ) {}

    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $rawKey = $this->extractKey($request);

        if (! $rawKey) {
            throw new ApiException('API key required.', 401, 'API_KEY_REQUIRED');
        }

        $prefix = substr($rawKey, 0, 16);
        $hash = hash('sha256', $rawKey);

        $apiKey = IntegrationApiKey::query()
            ->withoutGlobalScopes()
            ->where('key_prefix', $prefix)
            ->where('key_hash', $hash)
            ->where('is_active', true)
            ->first();

        if (! $apiKey) {
            throw new ApiException('Invalid API key.', 401, 'INVALID_API_KEY');
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            throw new ApiException('API key expired.', 401, 'API_KEY_EXPIRED');
        }

        if ($requiredScope && ! $apiKey->hasScope($requiredScope)) {
            throw new ApiException('API key scope insufficient.', 403, 'INSUFFICIENT_SCOPE');
        }

        $tenant = Tenant::query()->find($apiKey->tenant_id);
        if (! $tenant) {
            throw new ApiException('Tenant not found.', 404, 'TENANT_NOT_FOUND');
        }

        $this->tenantManager->set($tenant);
        $this->companyManager->set((int) $apiKey->company_id);
        $request->headers->set('X-Company-ID', (string) $apiKey->company_id);
        $request->attributes->set('integration_api_key', $apiKey);

        $actor = User::query()->find($apiKey->created_by);
        if ($actor) {
            auth('api')->setUser($actor);
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }

    protected function extractKey(Request $request): ?string
    {
        $header = $request->header('X-Api-Key');
        if ($header) {
            return trim($header);
        }

        $auth = $request->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            $token = trim(substr($auth, 7));
            if (str_starts_with($token, config('integration.api_key_prefix', 'cs_live_'))) {
                return $token;
            }
        }

        return null;
    }
}