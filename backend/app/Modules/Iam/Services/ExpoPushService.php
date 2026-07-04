<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    public function __construct(protected PushDeviceService $devices) {}

    public function isEnabled(): bool
    {
        return (bool) config('iam.expo_push.enabled', true);
    }

    public function shouldSendForType(string $type): bool
    {
        return str_starts_with($type, 'HR_');
    }

    public function sendToUser(
        User $user,
        string $type,
        string $title,
        string $body,
        array $payload = [],
    ): void {
        if (! $this->isEnabled() || ! $this->shouldSendForType($type)) {
            return;
        }

        $tokens = $this->devices->tokensForUser($user->id);
        if ($tokens->isEmpty()) {
            return;
        }

        $this->sendToTokens($tokens, $title, $body, array_merge($payload, [
            'type' => $type,
        ]));
    }

    /**
     * @param  Collection<int, string>|list<string>  $tokens
     */
    public function sendToTokens(Collection|array $tokens, string $title, string $body, array $data = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $tokenList = $tokens instanceof Collection ? $tokens->values()->all() : array_values($tokens);
        if ($tokenList === []) {
            return;
        }

        $messages = collect($tokenList)->map(fn (string $token) => [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'channelId' => config('iam.expo_push.android_channel_id', 'hr-alerts'),
            'data' => $data,
        ])->all();

        foreach (array_chunk($messages, 100) as $chunk) {
            $this->dispatchChunk($chunk);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    protected function dispatchChunk(array $messages): void
    {
        $url = config('iam.expo_push.api_url', 'https://exp.host/--/api/v2/push/send');
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $accessToken = config('iam.expo_push.access_token');
        if ($accessToken) {
            $headers['Authorization'] = "Bearer {$accessToken}";
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($headers)
                ->post($url, $messages);

            if (! $response->successful()) {
                Log::warning('Expo push request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $this->handleTicketResponse($response->json('data', []));
        } catch (\Throwable $e) {
            Log::warning('Expo push request error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     */
    protected function handleTicketResponse(array $tickets): void
    {
        $staleTokens = [];

        foreach ($tickets as $ticket) {
            if (($ticket['status'] ?? null) !== 'error') {
                continue;
            }

            $details = $ticket['details'] ?? [];
            if (($details['error'] ?? null) === 'DeviceNotRegistered') {
                $token = $ticket['to'] ?? $details['expoPushToken'] ?? null;
                if (is_string($token) && $token !== '') {
                    $staleTokens[] = $token;
                }
            }
        }

        if ($staleTokens !== []) {
            $this->devices->removeStaleTokens($staleTokens);
        }
    }
}