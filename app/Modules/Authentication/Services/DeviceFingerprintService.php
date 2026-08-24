<?php

namespace App\Modules\Authentication\Services;

use Illuminate\Http\Request;

class DeviceFingerprintService
{
    public function fromRequest(Request $request): string
    {
        return $this->fingerprint($request);
    }

    public function fingerprint(Request $request): string
    {
        $userAgent = strtolower(trim((string) ($request->userAgent() ?? 'unknown')));
        $subnet = $this->subnet((string) $request->ip());
        $sessionContext = sprintf('%s|%s', config('session.cookie'), config('app.name'));

        return hash_hmac('sha256', $userAgent.'|'.$subnet.'|'.$sessionContext, (string) config('app.key'));
    }

    protected function subnet(string $ipAddress): string
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ipAddress);

            if (count($parts) === 4) {
                return $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24';
            }
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $chunks = explode(':', $ipAddress);

            return implode(':', array_slice($chunks, 0, 4)).'::/64';
        }

        return 'unknown';
    }
}
