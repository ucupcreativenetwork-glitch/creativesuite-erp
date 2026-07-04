<?php

namespace App\Modules\Iam\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserActivationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $tempPassword,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akun CreativeSuite ERP Anda Telah Dibuat')
            ->line('Akun Anda telah disetujui dan dibuat oleh administrator.')
            ->line("Password sementara: {$this->tempPassword}")
            ->line('Anda wajib mengganti password saat login pertama.')
            ->action('Login', config('app.frontend_url', 'http://localhost:3000').'/login');
    }
}