<?php

namespace App\Modules\Rejected_Abyip\Console\Commands;

use App\Modules\Rejected_Abyip\Services\RejectedAbyipService;
use App\Services\SkFedPageCache;
use Illuminate\Console\Command;

class PurgeRejectedAbyipCommand extends Command
{
    protected $signature = 'rejected-abyip:purge';

    protected $description = 'Permanently delete rejected ABYIP submissions older than the retention window';

    public function handle(RejectedAbyipService $service): int
    {
        $purged = $service->purgeExpired(RejectedAbyipService::RETENTION_DAYS);

        if ($purged > 0) {
            app(SkFedPageCache::class)->bump('barangay.monitoring.index');
        }

        $this->info('Purged '.$purged.' expired rejected ABYIP record(s).');

        return self::SUCCESS;
    }
}
