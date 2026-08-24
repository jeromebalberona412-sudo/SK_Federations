<?php

namespace App\Modules\Profile\Notifications;

use App\Modules\Shared\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $user,
        private readonly string $plainToken,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('change-email.confirm', [
            'id' => $this->user->id,
            'token' => $this->plainToken,
        ], false));

        return (new MailMessage)
            ->subject('Confirm Your New SK OnePortal Email')
            ->greeting('Hello!')
            ->line('You requested to change the email address on your SK Federation account.')
            ->line('Current email: '.$this->user->email)
            ->line('New email: '.$this->user->pending_email)
            ->action('Confirm Email Change', $url)
            ->line('Your current email stays active until you confirm this link.')
            ->line('After confirming, you will be asked to set a new password for your account.')
            ->line('This link expires in 60 minutes. If you did not request this, you can ignore this email.');
    }
}
