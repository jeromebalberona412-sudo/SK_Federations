<?php

namespace App\Modules\Authentication\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class SkFedResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        \Illuminate\Support\Facades\Log::info('Building password reset email', [
            'user_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            'email' => $notifiable->getEmailForPasswordReset(),
            'type' => 'password_reset',
        ]);

        return (new MailMessage)
            ->subject('Reset Your SK Federation Password')
            ->greeting('Hello!')
            ->line('We received a request to reset the password for your SK Federation account.')
            ->action('Set New Password', $resetUrl)
            ->line('This password reset link will expire in '.$this->passwordExpiryLabel().'.')
            ->line('If you did not request a password reset, no further action is required.');
    }

    private function passwordExpiryLabel(): string
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);

        if ($minutes >= 1440) {
            $days = (int) round($minutes / 1440);

            return $days.' day'.($days === 1 ? '' : 's');
        }

        return $minutes.' minute'.($minutes === 1 ? '' : 's');
    }
}