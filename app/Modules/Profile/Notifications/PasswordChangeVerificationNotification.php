<?php

namespace App\Modules\Profile\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $plainToken,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('change-password.confirm', [
            'id' => $notifiable->id,
            'token' => $this->plainToken,
        ], false));

        return (new MailMessage)
            ->subject('Confirm Your SK OnePortal Password Change')
            ->greeting('Hello!')
            ->line('You requested to change the password on your SK Federation account.')
            ->line('Email: '.$notifiable->email)
            ->action('Confirm Password Change', $url)
            ->line('Your current password stays active until you confirm this link.')
            ->line('After confirming, you will be signed out and must log in again with your new password.')
            ->line('This link expires in 60 minutes. If you did not request this, you can ignore this email.');
    }
}
