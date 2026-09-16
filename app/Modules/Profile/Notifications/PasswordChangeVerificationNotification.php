<?php

namespace App\Modules\Profile\Notifications;

use App\Modules\Accounts\Support\PortalAppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $plainToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = PortalAppUrl::mailLink(
            PortalAppUrl::absoluteRoute('change-password.confirm', [
                'id' => $notifiable->id,
                'token' => $this->plainToken,
            ])
        );

        return (new MailMessage)
            ->subject('Confirm Your SK OnePortal Password Change')
            ->greeting('Hello!')
            ->line('You requested to change the password on your SK OnePortal account.')
            ->line('Email: '.$notifiable->email)
            ->line('This password is shared for SK Officials and SK Federations when you have access to both portals.')
            ->action('Confirm Password Change', $url)
            ->line('Your current password stays active until you confirm this link.')
            ->line('This email link only shows a confirmation page and will not sign you into the dashboard.')
            ->line('If SK Federations is still open on the verify page, that page will take you to the dashboard after you confirm.')
            ->line('This link expires in 60 minutes. If you did not request this, you can ignore this email.');
    }
}
