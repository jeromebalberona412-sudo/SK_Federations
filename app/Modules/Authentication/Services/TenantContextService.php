<?php

namespace App\Modules\Authentication\Services;

class TenantContextService
{
    public function __construct(
        protected SkFedTenantResolver $tenantResolver,
    ) {}

    public function tenantId(): ?int
    {
        return $this->tenantResolver->tenantId();
    }
}
