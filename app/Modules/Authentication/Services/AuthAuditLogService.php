<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Authentication\Models\AuthAuditLog;
use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthAuditLogService
{
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_FAILED = 'failed';
    public const OUTCOME_BLOCKED = 'blocked';

    public function __construct(
        protected TenantContextService $tenantContextService,
    ) {}

    public function log(
        string $event,
        ?User $user,
        Request $request,
        array $metadata = [],
        ?string $outcome = null,
        ?string $resourceType = null,
        mixed $resourceId = null,
    ): void {
        $resolvedOutcome = $this->resolveOutcome($event, $outcome);

        AuthAuditLog::query()->create([
            'tenant_id' => $user?->tenant_id ?? $this->tenantContextService->tenantId(),
            'user_id' => $user?->getKey(),
            'actor_email' => $user?->email,
            'event' => $event,
            'outcome' => $resolvedOutcome,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId === null ? null : (string) $resourceId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        if (Schema::hasTable('admin_activity_logs')) {
            $this->writeCentralAuditLog(
                $event,
                $user,
                $request,
                array_merge($metadata, ['outcome' => $resolvedOutcome]),
                $resourceType,
                $resourceId,
                $user?->tenant_id ?? $this->tenantContextService->tenantId(),
            );
        }

        Log::channel('sk_fed_auth_json')->info('sk_fed_auth_event', [
            'event' => $event,
            'user_id' => $user?->getKey(),
            'outcome' => $resolvedOutcome,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => $request->ip(),
            'metadata' => $metadata,
        ]);
    }

    protected function resolveOutcome(string $event, ?string $explicitOutcome = null): string
    {
        if (is_string($explicitOutcome) && in_array($explicitOutcome, [
            self::OUTCOME_SUCCESS,
            self::OUTCOME_FAILED,
            self::OUTCOME_BLOCKED,
        ], true)) {
            return $explicitOutcome;
        }

        if (str_contains($event, 'blocked')) {
            return self::OUTCOME_BLOCKED;
        }

        if (str_contains($event, 'failed') || str_contains($event, 'invalid')) {
            return self::OUTCOME_FAILED;
        }

        return self::OUTCOME_SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function writeCentralAuditLog(
        string $event,
        ?User $user,
        Request $request,
        array $metadata,
        ?string $resourceType,
        mixed $resourceId,
        ?int $tenantId,
    ): void {
        $metadata = array_merge($metadata, [
            'portal' => 'sk_federation',
            'module' => 'authentication',
            'role' => $user?->role,
            'email' => $user?->email,
        ]);

        DB::table('admin_activity_logs')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $user?->getKey(),
            'event_type' => $event,
            'action' => $event,
            'entity_type' => $resourceType,
            'entity_id' => $resourceId === null ? null : (string) $resourceId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => json_encode($metadata),
            'created_at' => now(),
        ]);
    }
}
