<?php

namespace App\Console\Commands;

use App\Modules\Deleted_SK_Officials\Services\DeletedSkOfficialsService;
use App\Services\SkFedPageCache;
use Illuminate\Console\Command;

class PurgeExpiredDeletedSkOfficials extends Command
{
    protected $signature = 'deleted-sk-officials:purge';

    protected $description = 'Permanently delete archived SK Officials that were not restored within 30 days';

    public function handle(DeletedSkOfficialsService $archiveService): int
    {
        $purged = $archiveService->purgeExpiredDeletedOfficials();

        if ($purged > 0) {
            app(SkFedPageCache::class)->bumpMany([
                'archive.deleted_sk_officials',
                'accounts.officials',
                'archive.sk_officials_records',
            ]);
        }

        $this->info('Purged '.$purged.' expired deleted SK Official record(s).');

        return self::SUCCESS;
    }
}
