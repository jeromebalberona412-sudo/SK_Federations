<?php

namespace App\Modules\Turnover\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TurnoverCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $portalUrl,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        \Illuminate\Support\Facades\Log::info('Building turnover completed email', [
            'user_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            'email' => isset($notifiable->email) ? $notifiable->email : 'unknown',
            'type' => 'turnover_completed',
        ]);

        return (new MailMessage)
            ->subject('Federation Turnover Completed')
            ->greeting('Hello!')
            ->line('The previous Federation Officers have completed the turnover.')
            ->line('Your administrative account is now active.')
            ->action('Login to SK One Portal', $this->portalUrl.'/login')
            ->line('Thank you for your service to the SK Federation.');
    }
}
