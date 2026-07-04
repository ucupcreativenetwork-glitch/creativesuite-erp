<?php

namespace App\Modules\Auth\Notifications;

use App\Modules\Auth\Services\EmailVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = EmailVerificationService::verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Your Email - CreativeSuite ERP')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email', $url)
            ->line('This link will expire in 60 minutes.');
    }
}