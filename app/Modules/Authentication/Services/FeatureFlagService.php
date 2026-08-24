<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Authentication\Models\FeatureFlag;

class FeatureFlagService
{
    /**
     * In-request flag cache — eliminates duplicate DB hits when the same flag
     * is checked multiple times during a single request (login flow calls
     * enabled() 3 times: device_verification, login_alert_notifications,
     * suspicious_login_detection).
     *
     * @var array<string, bool>
     */
    private array $cache = [];

    public function enabled(string $key): bool
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $default = (bool) data_get(config('sk_fed_auth.feature_flags'), $key, false);

        $flag = FeatureFlag::query()->where('flag_key', $key)->first();

        $resolved = $flag === null ? $default : (bool) $flag->enabled;

        $this->cache[$key] = $resolved;

        return $resolved;
    }

    public function deviceVerificationEnabled(): bool
    {
        return $this->enabled('features.device_verification');
    }

    /**
     * Pre-warm all feature flags used during authentication in a single query,
     * so subsequent enabled() calls are served from the in-request cache.
     * Call this once at the start of the login pipeline instead of letting
     * each service make its own round-trip.
     */
    public function preloadAuthFlags(): void
    {
        $authKeys = [
            'features.device_verification',
            'features.login_alert_notifications',
            'features.suspicious_login_detection',
        ];

        // Hydrate defaults from config first
        foreach ($authKeys as $key) {
            if (! array_key_exists($key, $this->cache)) {
                $this->cache[$key] = (bool) data_get(config('sk_fed_auth.feature_flags'), $key, false);
            }
        }

        // Single query for all flag rows — override defaults with DB values
        $rows = FeatureFlag::query()->whereIn('flag_key', $authKeys)->get(['flag_key', 'enabled']);

        foreach ($rows as $row) {
            $this->cache[(string) $row->flag_key] = (bool) $row->enabled;
        }
    }
}
