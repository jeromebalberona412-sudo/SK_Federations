<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PurgeDeletedSkOfficials extends Command
{
    protected $signature = 'sk-officials:purge-deleted';

    protected $description = 'Disabled: archived SK Officials are retained in deleted_sk_officials and are not auto-purged.';

    public function handle(): int
    {
        $this->warn('Automatic permanent deletion of archived SK Officials is disabled.');
        $this->info('Records remain in deleted_sk_officials until an authorized restore/manual process is used.');

        return self::SUCCESS;
    }
}
