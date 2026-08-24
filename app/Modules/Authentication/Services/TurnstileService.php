<?php

namespace App\Modules\Authentication\Services;

use Illuminate\Support\Facades\Http;

class TurnstileService
{
    public function isConfigured(): bool
    {
        if (! (bool) config('services.turnstile.enabled', false)) {
            return false;
        }

        $siteKey = trim((string) config('services.turnstile.site_key', ''));
        $secretKey = trim((string) config('services.turnstile.secret_key', ''));

        return $siteKey !== '' && $secretKey !== '';
    }

    public function verify(string $token, ?string $remoteIp = null): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        $secretKey = (string) config('services.turnstile.secret_key', '');

        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post((string) config('services.turnstile.verify_url'), [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]);

            if (! $response->ok()) {
                return false;
            }

            return (bool) data_get($response->json(), 'success', false);
        } catch (\Throwable) {
            return false;
        }
    }
}
