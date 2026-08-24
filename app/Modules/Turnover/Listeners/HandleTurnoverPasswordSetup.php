<?php

namespace App\Modules\Turnover\Listeners;

use App\Modules\Turnover\Services\TurnoverRegistrationService;
use Illuminate\Auth\Events\PasswordReset;

class HandleTurnoverPasswordSetup
{
    public function __construct(
        private readonly TurnoverRegistrationService $registrationService,
    ) {
    }

    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        if ($user === null || $user->turnover_status !== 'awaiting_setup') {
            return;
        }

        $this->registrationService->markAccountSetupCompleted($user);
    }
}
