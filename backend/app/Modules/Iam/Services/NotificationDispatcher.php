<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\User;
use App\Modules\Iam\Models\IamNotification;
use App\Modules\Iam\Models\UserCreationRequest;
use App\Modules\Iam\Notifications\UserRequestStatusNotification;
use Illuminate\Support\Collection;

class NotificationDispatcher
{
    public function __construct(
        protected WhatsAppNotifier $whatsApp,
        protected ExpoPushService $expoPush,
    ) {}

    public function notifyUsers(
        Collection $users,
        string $type,
        string $title,
        string $body,
        array $payload = [],
        bool $sendEmail = true,
    ): void {
        foreach ($users as $user) {
            IamNotification::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'channel' => 'IN_APP',
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'payload' => $payload,
                'sent_at' => now(),
            ]);

            if ($sendEmail) {
                $user->notify(new UserRequestStatusNotification($title, $body));
            }

            if (config('iam.whatsapp.enabled', false)) {
                IamNotification::query()->create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'channel' => 'WHATSAPP',
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'payload' => $payload,
                    'sent_at' => now(),
                ]);
                $this->whatsApp->send($user, $title, $body);
            }

            $this->expoPush->sendToUser($user, $type, $title, $body, $payload);
        }
    }

    public function notifyRequestPending(UserCreationRequest $request, Collection $approvers): void
    {
        $requester = $request->requester;
        $title = 'Permintaan user menunggu persetujuan';
        $body = "Permintaan {$request->request_number} dari {$requester?->full_name} menunggu persetujuan Anda.";

        $this->notifyUsers($approvers, 'USER_REQUEST_PENDING', $title, $body, [
            'request_public_id' => $request->public_id,
            'request_number' => $request->request_number,
        ]);
    }

    public function notifyRequester(UserCreationRequest $request, string $type, string $title, string $body): void
    {
        if ($request->requester) {
            $this->notifyUsers(collect([$request->requester]), $type, $title, $body, [
                'request_public_id' => $request->public_id,
                'request_number' => $request->request_number,
            ]);
        }
    }
}