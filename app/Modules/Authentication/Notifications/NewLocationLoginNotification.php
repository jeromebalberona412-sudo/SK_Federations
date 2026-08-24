<?php

namespace App\Modules\Authentication\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLocationLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $signals
     */
    public function __construct(
        protected string $ipAddress,
        protected ?string $userAgent,
        protected array $signals,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        \Illuminate\Support\Facades\Log::info('Building new location login email', [
            'user_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            'email' => $notifiable->email ?? 'unknown',
            'ip' => $this->ipAddress,
            'type' => 'new_location_login',
        ]);

        return (new MailMessage)
            ->subject('New Login Activity Detected')
            ->greeting('Security Alert')
            ->line('A new login to your SK Federation account was detected.')
            ->line('IP Address: '.$this->ipAddress)
            ->line('Device: '.substr((string) ($this->userAgent ?? 'unknown'), 0, 180))
            ->line('Signals: '.implode(', ', $this->signals))
            ->line('If this was not you, contact your administrator immediately.');
    }
}
