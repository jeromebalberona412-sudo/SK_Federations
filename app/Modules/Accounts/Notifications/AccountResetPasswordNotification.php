<?php

namespace App\Modules\Accounts\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $baseUrl,
        public string $audienceLabel,
        public bool $isAccountActivation = false,
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
        $baseUrl = rtrim($this->baseUrl, '/');
        $email = urlencode((string) $notifiable->getEmailForPasswordReset());
        $path = $this->isAccountActivation ? 'activate-account' : 'reset-password';
        $actionUrl = "{$baseUrl}/{$path}/{$this->token}?email={$email}";
        $name = trim((string) ($notifiable->name ?? '')) !== ''
            ? trim((string) $notifiable->name)
            : $this->audienceLabel;
        $loginLabel = $this->audienceLabel === 'SK Official' ? 'SK Officials' : $this->audienceLabel;

        if ($this->isAccountActivation) {
            return (new MailMessage)
                ->subject('Activate Your SK OnePortal Account')
                ->greeting('Hello '.$name.',')
                ->line('Your account has been created for the SK OnePortal system.')
                ->line('Please activate your account and set your password using the button below:')
                ->action('Activate / Set Up My Account', $actionUrl)
                ->line('This activation link is temporary and can only be used once. It expires in '.$this->passwordExpiryLabel().'.')
                ->line('Important: If the link expires, you do not need to ask the SK Federation to resend the activation email.')
                ->line('Simply go to the '.$loginLabel.' login page and select: Activate Account')
                ->line('Enter the email address registered to your account and request a new activation link.')
                ->line('If you did not expect this email, you can ignore it.');
        }

        $sentAt = now()->timezone(config('app.timezone', 'UTC'))->format('M j, Y g:i A');

        return (new MailMessage)
            ->subject('Set up your '.$this->audienceLabel.' account password ('.$sentAt.')')
            ->greeting('Hello!')
            ->line('You have been invited to set up a password for your '.$this->audienceLabel.' account.')
            ->line('Invitation sent on '.$sentAt.'.')
            ->action('Set Your Password', $actionUrl)
            ->line('This link will expire in '.$this->passwordExpiryLabel().'.')
            ->line('If you did not expect this email, you can ignore it.');
    }

    private function passwordExpiryLabel(): string
    {
        $minutes = $this->isAccountActivation
            ? (int) config('services.account_activation_expire_minutes', config('accounts.invitation_expire_minutes', 60 * 24))
            : (int) config('accounts.invitation_expire_minutes', 60 * 24);

        if ($minutes >= 1440) {
            $days = (int) round($minutes / 1440);

            return $days.' day'.($days === 1 ? '' : 's');
        }

        return $minutes.' minute'.($minutes === 1 ? '' : 's');
    }
}
