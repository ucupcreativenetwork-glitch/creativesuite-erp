<?php

namespace App\Modules\Integration\Services;

use App\Modules\Core\Models\User;
use App\Modules\Integration\Enums\WebhookEvent;
use App\Modules\Integration\Models\WebhookDelivery;
use App\Modules\Integration\Models\WebhookEndpoint;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use App\Support\Security\SafeUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookService
{
    use ChecksPermissions;

    public function list(User $user)
    {
        $this->assertPermission($user, 'int.webhook.read');

        return WebhookEndpoint::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(User $user, array $data): WebhookEndpoint
    {
        $this->assertPermission($user, 'int.webhook.manage');
        $this->validateEvents($data['events'] ?? []);
        SafeUrl::assertPublicHttpUrl($data['url']);

        return WebhookEndpoint::query()->create([
            'public_id' => (string) Str::uuid(),
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'name' => $data['name'],
            'url' => $data['url'],
            'secret' => Str::random(32),
            'events' => $data['events'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
        ]);
    }

    public function update(User $user, string $publicId, array $data): WebhookEndpoint
    {
        $this->assertPermission($user, 'int.webhook.manage');

        $endpoint = $this->findScoped($user, $publicId);

        if (isset($data['events'])) {
            $this->validateEvents($data['events']);
        }

        if (isset($data['url'])) {
            SafeUrl::assertPublicHttpUrl($data['url']);
        }

        $endpoint->update(array_filter([
            'name' => $data['name'] ?? null,
            'url' => $data['url'] ?? null,
            'events' => $data['events'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        return $endpoint->fresh();
    }

    public function destroy(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'int.webhook.manage');
        $this->findScoped($user, $publicId)->delete();
    }

    public function dispatch(int $tenantId, int $companyId, WebhookEvent|string $event, array $payload): void
    {
        $eventValue = $event instanceof WebhookEvent ? $event->value : $event;

        $endpoints = WebhookEndpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $e) => $e->listensTo($eventValue));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $eventValue,
                'payload' => $payload,
                'status' => 'PENDING',
                'attempts' => 0,
            ]);

            $this->attemptDelivery($delivery, $endpoint);
        }
    }

    public function listDeliveries(User $user, string $webhookPublicId, array $filters = [])
    {
        $this->assertPermission($user, 'int.webhook.read');

        $endpoint = $this->findScoped($user, $webhookPublicId);

        return WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->id)
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 25);
    }

    public function retryPending(): int
    {
        $maxAttempts = (int) config('integration.webhook.max_attempts', 5);
        $retried = 0;

        WebhookDelivery::query()
            ->where('status', 'FAILED')
            ->where('attempts', '<', $maxAttempts)
            ->where(function ($q): void {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->limit(50)
            ->get()
            ->each(function (WebhookDelivery $delivery) use (&$retried): void {
                $endpoint = $delivery->endpoint;
                if ($endpoint?->is_active) {
                    $this->attemptDelivery($delivery, $endpoint);
                    $retried++;
                }
            });

        return $retried;
    }

    protected function attemptDelivery(WebhookDelivery $delivery, WebhookEndpoint $endpoint): void
    {
        SafeUrl::assertPublicHttpUrl($endpoint->url);

        $body = json_encode([
            'event' => $delivery->event,
            'timestamp' => now()->toIso8601String(),
            'data' => $delivery->payload,
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $body, $endpoint->secret);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-CreativeSuite-Signature' => $signature,
                    'X-CreativeSuite-Event' => $delivery->event,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $delivery->update([
                'attempts' => $delivery->attempts + 1,
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 2000),
                'status' => $response->successful() ? 'DELIVERED' : 'FAILED',
                'delivered_at' => $response->successful() ? now() : null,
                'next_retry_at' => $response->successful()
                    ? null
                    : now()->addMinutes((int) config('integration.webhook.retry_delay_minutes', 5)),
            ]);
        } catch (\Throwable $e) {
            $delivery->update([
                'attempts' => $delivery->attempts + 1,
                'response_status' => 0,
                'response_body' => substr($e->getMessage(), 0, 500),
                'status' => 'FAILED',
                'next_retry_at' => now()->addMinutes((int) config('integration.webhook.retry_delay_minutes', 5)),
            ]);
        }
    }

    protected function validateEvents(array $events): void
    {
        $available = config('integration.webhook_events', []);
        foreach ($events as $event) {
            if (! in_array($event, $available, true)) {
                throw new ApiException("Invalid webhook event: {$event}", 422, 'INVALID_EVENT');
            }
        }
    }

    protected function findScoped(User $user, string $publicId): WebhookEndpoint
    {
        return WebhookEndpoint::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}