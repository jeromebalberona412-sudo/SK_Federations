<?php

namespace App\Modules\Turnover\Services;

use App\Modules\Shared\Models\User;
use App\Modules\Turnover\Models\FederationTurnoverRegistration;
use App\Modules\Turnover\Notifications\TurnoverAccountSetupNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class TurnoverInvitationService
{
    public function sendSetupInvitation(User $user, FederationTurnoverRegistration $registration): void
    {
        try {
            DB::table((string) config('auth.passwords.users.table', 'password_reset_tokens'))
                ->where('email', strtolower((string) $user->email))
                ->delete();

            $token = Password::broker()->createToken($user);
            $baseUrl = rtrim((string) config('app.url'), '/');

            Notification::sendNow(
                $user,
                new TurnoverAccountSetupNotification($token, $baseUrl, $registration->position)
            );

            $registration->forceFill([
                'status' => FederationTurnoverRegistration::STATUS_INVITED,
                'invited_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            Log::error('Failed to send turnover account setup email.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Unable to send account setup email. Please check mail settings and try again.',
            ]);
        }
    }
}
