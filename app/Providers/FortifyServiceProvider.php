<?php

namespace App\Providers;

use App\Modules\Authentication\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Modules\Authentication\Services\AuthenticationService;
use App\Modules\Authentication\Services\TurnstileAttemptGuard;
use App\Modules\Shared\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::loginView(function (Request $request) {
            $guard = app(TurnstileAttemptGuard::class);

            return view('authentication::login', [
                'turnstileRequired' => $guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request)
                    || (bool) $request->session()->get('turnstile_required', false),
            ]);
        });

        Fortify::verifyEmailView(function (Request $request) {
            /** @var User|null $user */
            $user = $request->user();

            if ($user === null) {
                throw new HttpResponseException(redirect()->route('login'));
            }

            if ($user->hasVerifiedEmail()) {
                throw new HttpResponseException(redirect()->route('dashboard'));
            }

            Auth::logout();

            app(AuthenticationService::class)->restoreVerificationPending($user->fresh(), $request);
            $request->session()->flash('status', 'Please verify your email to continue.');

            throw new HttpResponseException(redirect()->route('skfed.verification.wait'));
        });

        Fortify::authenticateUsing(function (Request $request) {
            $turnstileGuard = app(TurnstileAttemptGuard::class);

            if ($fail = $turnstileGuard->enforce(TurnstileAttemptGuard::ACTION_SIGNIN, $request)) {
                $request->session()->flash('turnstile_required', true);

                throw ValidationException::withMessages([
                    'email' => [$fail],
                    'password' => [$fail],
                ])->redirectTo(route('login'));
            }

            $user = app(AuthenticationService::class)->authenticate($request);

            if ($user === null) {
                if ($request->session()->has('sk_fed_email_verification_pending')) {
                    $turnstileGuard->clear(TurnstileAttemptGuard::ACTION_SIGNIN, $request);

                    throw new HttpResponseException(
                        redirect()->route('skfed.verification.wait')
                    );
                }

                $attempt = $turnstileGuard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
                $message = $attempt['message'] ?? 'Invalid Email or Password';
                $request->session()->flash('turnstile_required', (bool) $attempt['turnstile_required']);

                throw ValidationException::withMessages([
                    'email' => [$message],
                    'password' => [$message],
                ]);
            }

            $turnstileGuard->clear(TurnstileAttemptGuard::ACTION_SIGNIN, $request);

            return $user;
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User && request()->hasSession()) {
                app(AuthenticationService::class)->clearSessionOwnershipOnLogout($event->user, request());
            }
        });
    }
}
