<?php

namespace App\Modules\KabataanMonitoring\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KabataanAccountInviteNotification extends Notification
{
    public function __construct(
        public string $fullName,
        public string $activationUrl,
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
        $name = trim($this->fullName) !== '' ? $this->fullName : 'Kabataan';

        return (new MailMessage)
            ->subject('Activate Your Kabataan OnePortal Account')
            ->greeting('Hello '.$name.',')
            ->line('Your account has been created for the SK OnePortal system.')
            ->line('Please activate your account and set your password using the button below:')
            ->action('Activate / Set Up My Account', $this->activationUrl)
            ->line('This activation link is temporary and can only be used once. It expires in '.$this->expiryLabel().'.')
            ->line('Important: If the link expires, you do not need to ask SK Federation or SK Officials to resend the activation email.')
            ->line('Simply go to the Kabataan login page and select: Activate Account')
            ->line('Enter the email address registered to your account and request a new activation link.')
            ->line('If you did not expect this email, please contact your SK Federation.');
    }

    private function expiryLabel(): string
    {
        $minutes = max(1, (int) config('services.account_activation_expire_minutes', 1440));

        if ($minutes >= 1440) {
            $days = (int) round($minutes / 1440);

            return $days.' day'.($days === 1 ? '' : 's');
        }

        return $minutes.' minute'.($minutes === 1 ? '' : 's');
    }
}
