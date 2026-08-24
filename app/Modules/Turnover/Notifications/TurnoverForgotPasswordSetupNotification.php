<?php

namespace App\Modules\Turnover\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TurnoverForgotPasswordSetupNotification extends Notification
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
        $expiryLabel = $this->passwordExpiryLabel();

        \Illuminate\Support\Facades\Log::info('Building turnover forgot password setup email', [
            'user_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            'email' => $notifiable->getEmailForPasswordReset(),
            'position' => $this->position,
            'type' => 'turnover_forgot_password_setup',
        ]);

        return (new MailMessage)
            ->subject('Complete Your SK Federation Account Setup')
            ->greeting('Hello!')
            ->line('You requested a new password setup link for your incoming SK Federation '.$this->position.' account.')
            ->line('If your original setup email expired after 24 hours, you can use this link to set your password and complete account setup.')
            ->action('Set Up Account', $setupUrl)
            ->line('This link will expire in '.$expiryLabel.'.')
            ->line('After setting your password, please wait until the outgoing Federation officers complete the turnover before logging in.');
    }

    private function passwordExpiryLabel(): string
    {
        $brokerName = (string) config('fortify.passwords', config('auth.defaults.passwords', 'users'));
        $minutes = (int) config('auth.passwords.'.$brokerName.'.expire', 60 * 24);

        if ($minutes >= 1440) {
            $days = (int) round($minutes / 1440);

            return $days.' day'.($days === 1 ? '' : 's');
        }

        return $minutes.' minute'.($minutes === 1 ? '' : 's');
    }
}
