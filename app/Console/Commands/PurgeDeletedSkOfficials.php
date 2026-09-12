<?php

namespace App\Console\Commands;

use App\Modules\Archive_Management\Services\DeletedArchiveService;
use Illuminate\Console\Command;

class PurgeDeletedSkOfficials extends Command
{
    protected $signature = 'sk-officials:purge-deleted';

    protected $description = 'Permanently delete soft-deleted SK Officials older than 30 days';

    public function handle(DeletedArchiveService $deletedArchiveService): int
    {
        $purged = $deletedArchiveService->purgeExpiredDeletedOfficials();

        $this->info('Purged '.$purged.' deleted SK Official account(s).');

        return self::SUCCESS;
    }
}
