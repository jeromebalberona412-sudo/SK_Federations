<?php

namespace App\Providers;

use App\Modules\Authentication\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Modules\Shared\Models\User;
use App\Modules\Authentication\Services\AuthenticationService;
use Laravel\Fortify\Contracts\LoginResponse;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('authentication::login'));
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
            // ── Turnstile verification ─────────────────────────────────────────
            // Must happen before credential checking so an invalid/missing token
            // never reaches the authentication layer.
            $turnstileService = app(\App\Modules\Authentication\Services\TurnstileService::class);

            if ($turnstileService->isConfigured()) {
                $turnstileToken = (string) $request->input('cf-turnstile-response', '');

                if ($turnstileToken === '') {
                    throw ValidationException::withMessages([
                        'email'    => ['Please complete the Cloudflare verification first.'],
                        'password' => ['Please complete the Cloudflare verification first.'],
                    ])->redirectTo(route('login'));
                }

                if (! $turnstileService->verify($turnstileToken, $request->ip())) {
                    throw ValidationException::withMessages([
                        'email'    => ['Cloudflare verification failed. Please try again.'],
                        'password' => ['Cloudflare verification failed. Please try again.'],
                    ])->redirectTo(route('login'));
                }
            }

            // ── Credential check ──────────────────────────────────────────────
            $user = app(AuthenticationService::class)->authenticate($request);

            if ($user === null) {
                if ($request->session()->has('sk_fed_email_verification_pending')) {
                    throw new HttpResponseException(
                        redirect()->route('skfed.verification.wait')
                    );
                }

                throw ValidationException::withMessages([
                    'email' => ['Invalid Email or Password'],
                    'password' => ['Invalid Email or Password'],
                ]);
            }

            return $user;
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User && request()->hasSession()) {
                app(AuthenticationService::class)->clearSessionOwnershipOnLogout($event->user, request());
            }
        });
    }
}
