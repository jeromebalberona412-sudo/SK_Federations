<?php

namespace App\Console\Commands;

use App\Modules\SK_Officials_Records\Services\ArchivedTermRecordsBackfillService;
use App\Services\SkFedPageCache;
use Illuminate\Console\Command;

class BackfillArchivedTermRecordsCommand extends Command
{
    protected $signature = 'sk-fed:backfill-archived-term-records {--tenant= : Optional tenant id}';

    protected $description = 'INSERT-only backfill of completed SK terms into Officials/Federation Records tables (never deletes data)';

    public function handle(ArchivedTermRecordsBackfillService $backfillService): int
    {
        $tenantOption = $this->option('tenant');
        $tenantId = ($tenantOption !== null && $tenantOption !== '')
            ? (int) $tenantOption
            : null;

        $result = $backfillService->backfill($tenantId);

        if (($result['officials_inserted'] + $result['federation_inserted']) > 0) {
            app(SkFedPageCache::class)->bumpMany([
                'archive.sk_officials_records',
                'archive.sk_federation_records',
                'accounts.officials',
                'accounts.federation',
            ]);
        }

        $this->info('SK Officials Records inserted: '.$result['officials_inserted']);
        $this->info('SK Federation Records inserted: '.$result['federation_inserted']);
        $this->line('Existing archive rows were preserved (no deletes / no drops).');

        return self::SUCCESS;
    }
}
