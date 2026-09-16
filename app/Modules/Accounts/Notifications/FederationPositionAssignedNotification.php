<?php

namespace App\Modules\Accounts\Notifications;

use App\Modules\Accounts\Support\PortalAppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FederationPositionAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $federationPosition,
        public string $portalUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $position = $this->federationPosition;
        $portalUrl = PortalAppUrl::publicBase($this->portalUrl);
        $sentAt = now()->timezone(config('app.timezone', 'Asia/Manila'))->format('M j, Y g:i A');

        return (new MailMessage)
            ->subject("You have been assigned as SK Federation {$position}")
            ->greeting('Hello!')
            ->line("You have been assigned as {$position} of the SK Federation.")
            ->line('Use your existing SK Officials email and password to sign in to the SK Federation portal. No new login account was created.')
            ->line('You may now access federation tools, notifications, audit logs, and other assigned features.')
            ->action('Open SK Federation Portal', $portalUrl)
            ->line('Assignment recorded on '.$sentAt.'.')
            ->line('If you did not expect this assignment, please contact your SK Federation administrator.');
    }
}
