<?php

namespace App\Modules\Authentication\Providers;

use App\Modules\Authentication\Services\AuthAuditLogService;
use App\Modules\Authentication\Services\AuthenticationService;
use App\Modules\Authentication\Services\DeviceFingerprintService;
use App\Modules\Authentication\Services\EmailVerificationDeviceService;
use App\Modules\Authentication\Services\FeatureFlagService;
use App\Modules\Authentication\Services\LoginSecurityService;
use App\Modules\Authentication\Services\PasswordResetService;
use App\Modules\Authentication\Services\SuspiciousLoginService;
use App\Modules\Authentication\Services\TenantContextService;
use App\Modules\Authentication\Services\TurnstileService;
use App\Modules\Authentication\Services\TrustedDeviceService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AuthenticationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContextService::class);
        $this->app->singleton(FeatureFlagService::class);
        $this->app->singleton(AuthAuditLogService::class);
        $this->app->singleton(DeviceFingerprintService::class);
        $this->app->singleton(EmailVerificationDeviceService::class);
        $this->app->singleton(TrustedDeviceService::class);
        $this->app->singleton(LoginSecurityService::class);
        $this->app->singleton(SuspiciousLoginService::class);
        $this->app->singleton(AuthenticationService::class);
        $this->app->singleton(PasswordResetService::class);
        $this->app->singleton(TurnstileService::class);
    }

    public function boot(): void
    {
        $this->configurePasswordResetRateLimiters();
        $this->loadRoutes();
        $this->loadViewsFrom(__DIR__.'/../Views', 'authentication');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/authentication'),
        ], 'auth-assets');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../Routes/auth.php');
    }

    protected function configurePasswordResetRateLimiters(): void
    {
        $ipLimitPerMinute = max(1, (int) config('sk_fed_auth.password_reset.rate_limit.ip_per_minute', 5));
        $emailLimitPerHour = max(1, (int) config('sk_fed_auth.password_reset.rate_limit.email_per_hour', 10));
        $resetFormLimitPerMinute = max(1, (int) config('sk_fed_auth.password_reset.rate_limit.reset_form_per_minute', 20));

        RateLimiter::for('skfed-password-reset-ip', function (Request $request) use ($ipLimitPerMinute) {
            return Limit::perMinute($ipLimitPerMinute)
                ->by('skfed-password-reset-ip:'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->rateLimitedResponse($request, $headers, 'ip'));
        });

        RateLimiter::for('skfed-password-reset-email', function (Request $request) use ($emailLimitPerHour) {
            $normalizedEmail = Str::lower(trim((string) $request->input('email', '')));
            $emailHash = $normalizedEmail === '' ? 'missing' : hash('sha256', $normalizedEmail);

            return Limit::perHour($emailLimitPerHour)
                ->by('skfed-password-reset-email:'.$emailHash)
                ->response(fn (Request $request, array $headers) => $this->rateLimitedResponse($request, $headers, 'email'));
        });

        RateLimiter::for('skfed-password-reset-form', function (Request $request) use ($resetFormLimitPerMinute) {
            return Limit::perMinute($resetFormLimitPerMinute)
                ->by('skfed-password-reset-form:'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->rateLimitedResponse($request, $headers, 'form'));
        });
    }

    protected function rateLimitedResponse(Request $request, array $headers, string $scope)
    {
        $email = Str::lower(trim((string) $request->input('email', '')));
        $emailHash = $email === '' ? null : hash('sha256', $email);

        app(AuthAuditLogService::class)->log(
            event: 'password_reset_failed',
            user: null,
            request: $request,
            metadata: [
                'reason' => 'rate_limited',
                'scope' => $scope,
                'email_hash' => $emailHash,
            ],
            outcome: AuthAuditLogService::OUTCOME_BLOCKED,
            resourceType: 'password_reset',
            resourceId: $emailHash ?? $request->ip(),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Too many password reset attempts. Please try again later.',
            ], 429, $headers);
        }

        return redirect()->back()
            ->withErrors(['email' => 'Too many password reset attempts. Please try again later.'])
            ->setStatusCode(429)
            ->withHeaders($headers);
    }
}
