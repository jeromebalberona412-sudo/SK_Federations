<?php

namespace App\Providers;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Policies\AccountPolicy;
use App\Modules\Authentication\Services\BootstrapSkFedAdminService;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure PHP temp directory is used consistently
        // The container configures /tmp/php-tmp via php-fpm.conf and entrypoint.sh
        // This prevents tempnam() from falling back to system temp dir
        $tempDir = ini_get('sys_temp_dir') ?: sys_get_temp_dir();
        if ($tempDir && is_writable($tempDir)) {
            // Sync environment variables with PHP configuration
            putenv('TMPDIR='.$tempDir);
            putenv('TEMP='.$tempDir);
            putenv('TMP='.$tempDir);
        }

        Gate::policy(User::class, AccountPolicy::class);
        Gate::policy(OfficialProfile::class, AccountPolicy::class);
        Gate::define('manage-accounts', fn (User $user) => $user->isFederationAdministrator());

        $applicationUrl = trim((string) config('app.url'));

        // In local dev, match the browser host (127.0.0.1 vs LAN IP) so Vite/CSS/assets load.
        if ($this->app->environment('local') && ! $this->app->runningInConsole()) {
            $requestRoot = request()->getSchemeAndHttpHost();
            if ($requestRoot !== '') {
                URL::forceRootUrl($requestRoot);
            }
        } elseif ($applicationUrl !== '' && filter_var($applicationUrl, FILTER_VALIDATE_URL)) {
            URL::forceRootUrl($applicationUrl);

            if (str_starts_with(strtolower($applicationUrl), 'https://')) {
                URL::forceScheme('https');
            } elseif (str_starts_with(strtolower($applicationUrl), 'http://')) {
                URL::forceScheme('http');
            }
        }

        if (
            isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        ) {
            URL::forceScheme('https');
        }

        View::composer([
            'layout::header',
            'layout::sidebar',
            'community_feed::*',
        ], function ($view): void {
            /** @var User|null $user */
            $user = Auth::user();

            $user?->loadMissing('officialProfile');

            $view->with([
                'user' => $user,
                'avatar' => asset('Images/SK_Fed_profile.png'),
                'displayName' => $this->resolveSidebarDisplayName($user),
                'sidebarMeta' => $this->resolveSidebarMeta($user),
                'formattedRole' => match (true) {
                    $user && strtolower((string) $user->email) === BootstrapSkFedAdminService::bootstrapEmailNormalized() => 'Admin',
                    $user?->isSkFed() => 'SK Federation',
                    $user?->hasFederationLeadershipAccess() => (string) ($user->officialProfile?->federation_position ?? 'SK Federation'),
                    (string) ($user?->role ?? '') === 'admin' => 'Administrator',
                    default => $user?->role
                        ? ucwords(str_replace('_', ' ', (string) $user->role))
                        : 'SK Federation',
                },
            ]);
        });
    }

    private function resolveSidebarDisplayName(?User $user): string
    {
        if (! $user) {
            return 'User';
        }

        if (strtolower((string) $user->email) === BootstrapSkFedAdminService::bootstrapEmailNormalized()) {
            return 'Admin';
        }

        $profile = $user->officialProfile;
        if ($profile) {
            $parts = array_filter([
                trim((string) $profile->first_name),
                trim((string) $profile->middle_name),
                trim((string) $profile->last_name),
                trim((string) $profile->suffix),
            ], fn ($part) => $part !== '');

            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        $name = trim((string) $user->name);

        return $name !== '' ? $name : 'User';
    }

    /**
     * @return array<string, string>|null
     */
    private function resolveSidebarMeta(?User $user): ?array
    {
        if (! $user?->hasFederationLeadershipAccess()) {
            return null;
        }

        $profile = $user->officialProfile;
        if ($profile === null) {
            return null;
        }

        $position = trim((string) ($profile->federation_position ?? ''));
        if (! in_array($position, OfficialProfile::FEDERATION_PORTAL_ACCESS_POSITIONS, true)) {
            return null;
        }

        $term = $profile->terms()
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('official_terms', 'status'),
                fn ($query) => $query->where('status', OfficialTerm::STATUS_ACTIVE)
            )
            ->orderByDesc('term_start')
            ->first();

        $formatTerm = static function ($value): string {
            if ($value === null || $value === '') {
                return 'N/A';
            }

            try {
                return \Illuminate\Support\Carbon::parse($value)->format('M d, Y');
            } catch (\Throwable) {
                return 'N/A';
            }
        };

        $contact = trim((string) ($profile->contact_number ?? ''));
        $sex = trim((string) ($profile->sex ?? ''));

        return array_filter([
            'position' => $position,
            'term_start' => $formatTerm($term?->term_start),
            'term_end' => $formatTerm($term?->term_end),
            'contact_number' => $contact !== '' ? $contact : null,
            'sex' => $sex !== '' ? $sex : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
