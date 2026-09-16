<?php

namespace App\Modules\Profile\Notifications;

use App\Modules\Accounts\Support\PortalAppUrl;
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
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = PortalAppUrl::mailLink(
            PortalAppUrl::absoluteRoute('change-email.confirm', [
                'id' => $this->user->id,
                'token' => $this->plainToken,
            ])
        );

        return (new MailMessage)
            ->subject('Confirm Your New SK OnePortal Email')
            ->greeting('Hello!')
            ->line('You requested to change the email address on your SK OnePortal account.')
            ->line('Current email: '.$this->user->email)
            ->line('New email: '.$this->user->pending_email)
            ->line('This email is shared for SK Officials and SK Federations when you have access to both portals.')
            ->action('Confirm Email Change', $url)
            ->line('Your current email stays active until you confirm this link.')
            ->line('This email link only shows a confirmation page and will not sign you into the dashboard.')
            ->line('If SK Federations is still open on the verify page, that page will take you to the dashboard after you confirm.')
            ->line('This link expires in 60 minutes. If you did not request this, you can ignore this email.');
    }
}
