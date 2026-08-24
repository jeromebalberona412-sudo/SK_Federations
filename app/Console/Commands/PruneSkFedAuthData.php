<?php

namespace App\Console\Commands;

use App\Modules\Authentication\Models\AuthAuditLog;
use App\Modules\Authentication\Models\LoginAttempt;
use App\Modules\Authentication\Models\TrustedDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneSkFedAuthData extends Command
{
    protected $signature = 'sk-fed:auth:prune';

    protected $description = 'Prune expired SK FED authentication security data';

    public function handle(): int
    {
        $expiredDevices = TrustedDevice::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $attemptRetentionDays = (int) config('sk_fed_auth.retention.attempt_days', 90);
        $auditRetentionDays = (int) config('sk_fed_auth.retention.audit_days', 180);
        $sessionRetentionDays = (int) config('sk_fed_auth.retention.session_days', 30);

        $oldAttempts = LoginAttempt::query()
            ->where('attempted_at', '<', now()->subDays($attemptRetentionDays))
            ->delete();

        $oldAudit = AuthAuditLog::query()
            ->where('created_at', '<', now()->subDays($auditRetentionDays))
            ->delete();

        $oldSessions = DB::table(config('session.table', 'sessions'))
            ->where('last_activity', '<', now()->subDays($sessionRetentionDays)->timestamp)
            ->delete();

        $this->info('Pruned SK FED auth data.');
        $this->line("Expired devices: {$expiredDevices}");
        $this->line("Old attempts: {$oldAttempts}");
        $this->line("Old audit logs: {$oldAudit}");
        $this->line("Old sessions: {$oldSessions}");

        return self::SUCCESS;
    }
}
