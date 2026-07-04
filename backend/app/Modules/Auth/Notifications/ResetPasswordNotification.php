<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $token,
        protected string $companyName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $expire = config('auth_activation.password_reset_expire_minutes', 30);
        $url = $frontendUrl.'/reset-password?token='.$this->token
            .'&email='.urlencode($notifiable->email)
            .'&company_name='.urlencode($this->companyName);

        return (new MailMessage)
            ->subject('Reset Password - CreativeSuite ERP')
            ->line('You are receiving this email because we received a password reset request.')
            ->action('Reset Password', $url)
            ->line('This link will expire in '.$expire.' minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}