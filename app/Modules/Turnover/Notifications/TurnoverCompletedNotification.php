<?php

namespace App\Modules\Turnover\Notifications;

use App\Modules\Accounts\Support\PortalAppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class TurnoverCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
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
        $loginUrl = PortalAppUrl::join(PortalAppUrl::publicBase($this->portalUrl), '/login');

        Log::info('Building turnover completed email', [
            'user_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            'email' => isset($notifiable->email) ? $notifiable->email : 'unknown',
            'type' => 'turnover_completed',
        ]);

        return (new MailMessage)
            ->subject('Federation Turnover Completed')
            ->greeting('Hello!')
            ->line('The previous Federation Officers have completed the turnover.')
            ->line('Your administrative account is now active.')
            ->action('Login to SK One Portal', $loginUrl)
            ->line('Thank you for your service to the SK Federation.');
    }
}
