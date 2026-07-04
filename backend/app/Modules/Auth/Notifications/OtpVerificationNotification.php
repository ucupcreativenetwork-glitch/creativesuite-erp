<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $otpCode) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth_activation.otp_expire_minutes', 5);

        return (new MailMessage)
            ->subject('🔐 Kode Verifikasi OTP — CreativeSuite ERP')
            ->view('mail.otp', [
                'badge' => 'Verifikasi OTP',
                'otpCode' => $this->otpCode,
                'minutes' => $minutes,
            ]);
    }
}