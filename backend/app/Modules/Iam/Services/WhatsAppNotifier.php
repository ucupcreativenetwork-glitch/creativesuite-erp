<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotifier
{
    public function send(User $user, string $title, string $body): void
    {
        if (! config('iam.whatsapp.enabled', false)) {
            return;
        }

        $phone = $user->phone;
        if (! $phone) {
            return;
        }

        $webhookUrl = config('iam.whatsapp.webhook_url');
        if (! $webhookUrl) {
            Log::info('IAM WhatsApp skipped: webhook URL not configured', ['user_id' => $user->id]);

            return;
        }

        try {
            Http::timeout(10)->post($webhookUrl, [
                'phone' => $phone,
                'title' => $title,
                'message' => "{$title}\n\n{$body}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('IAM WhatsApp delivery failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}