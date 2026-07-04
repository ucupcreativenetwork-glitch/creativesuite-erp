<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountActivationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $activationUrl,
        protected string $fullName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hours = config('auth_activation.activation_token_hours', 24);

        return (new MailMessage)
            ->subject('🎉 Aktivasi Akun CreativeSuite ERP')
            ->view('mail.activation', [
                'badge' => 'Aktivasi Akun',
                'fullName' => $this->fullName,
                'activationUrl' => $this->activationUrl,
                'hours' => $hours,
            ]);
    }
}