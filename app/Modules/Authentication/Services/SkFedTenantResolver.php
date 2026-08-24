<?php

namespace App\Modules\Authentication\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SkFedTenantResolver
{
    protected ?int $cachedTenantId = null;

    /**
     * @return list<string>
     */
    public function candidateTenantCodes(): array
    {
        $configured = config('sk_fed_auth.tenant_code');

        return array_values(array_unique(array_filter([
            is_string($configured) && $configured !== '' ? $configured : null,
            'santa_cruz',
            'santa-cruz-federation',
        ])));
    }

    public function tenantId(): ?int
    {
        if ($this->cachedTenantId !== null) {
            return $this->cachedTenantId;
        }

        if (! Schema::hasTable('tenants')) {
            return null;
        }

        foreach ($this->candidateTenantCodes() as $code) {
            $tenantId = DB::table('tenants')->where('code', $code)->value('id');

            if ($tenantId !== null) {
                $this->cachedTenantId = (int) $tenantId;

                return $this->cachedTenantId;
            }
        }

        return null;
    }

    public function ensureTenantExists(): int
    {
        $tenantId = $this->tenantId();

        if ($tenantId !== null) {
            return $tenantId;
        }

        $code = (string) config('sk_fed_auth.tenant_code', 'santa_cruz');

        if ($code === '') {
            $code = 'santa_cruz';
        }

        $now = now();

        $this->cachedTenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Santa Cruz Federation',
            'code' => $code,
            'municipality' => 'Santa Cruz',
            'province' => 'Laguna',
            'region' => 'IV-A CALABARZON',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->cachedTenantId;
    }
}
