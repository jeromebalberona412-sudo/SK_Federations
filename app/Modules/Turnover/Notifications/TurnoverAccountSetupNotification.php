<?php

namespace App\Modules\Turnover\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TurnoverAccountSetupNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $baseUrl,
        public string $position,
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
        $baseUrl = rtrim($this->baseUrl, '/');
        $email = urlencode((string) $notifiable->getEmailForPasswordReset());
        $setupUrl = "{$baseUrl}/reset-password/{$this->token}?email={$email}";
        $expiryHours = max(1, (int) round((int) config('turnover.invitation_expire_minutes', 1440) / 60));

        \Illuminate\Support\Facades\Log::info('Building turnover account setup email', [
            'user_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            'email' => $notifiable->getEmailForPasswordReset(),
            'position' => $this->position,
            'type' => 'turnover_account_setup',
        ]);

        return (new MailMessage)
            ->subject('SK One Portal Account Setup')
            ->greeting('Congratulations!')
            ->line('You have been registered as the incoming SK Federation '.$this->position.'.')
            ->line('Please click the button below to set up your account.')
            ->action('Set Up Account', $setupUrl)
            ->line("This link will expire in {$expiryHours} hour".($expiryHours === 1 ? '' : 's').'.')
            ->line('If you did not expect this email, you can ignore it.');
    }
}
