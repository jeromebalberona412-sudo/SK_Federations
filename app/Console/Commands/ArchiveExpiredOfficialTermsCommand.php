<?php

namespace App\Console\Commands;

use App\Modules\Shared\Models\Tenant;
use App\Modules\SK_Officials_Records\Services\ArchivedTermRecordsBackfillService;
use App\Modules\SK_Officials_Records\Services\ExpiredTermProcessorService;
use App\Services\SkFedPageCache;
use Illuminate\Console\Command;
use Throwable;

class ArchiveExpiredOfficialTermsCommand extends Command
{
    protected $signature = 'sk-fed:archive-expired-terms {--tenant= : Optional tenant id}';

    protected $description = 'Archive ended SK Official / Federation terms into Records and remove them from Manage Account lists';

    public function handle(
        ExpiredTermProcessorService $processor,
        ArchivedTermRecordsBackfillService $backfillService,
    ): int {
        $tenantOption = $this->option('tenant');
        $tenantIds = $tenantOption !== null && $tenantOption !== ''
            ? [(int) $tenantOption]
            : Tenant::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($tenantIds === []) {
            $processed = $processor->processForTenant(null);
            $backfill = $backfillService->backfill(null);
            $this->info('Processed '.$processed.' expired term(s) across all tenants.');
            $this->info('Backfill officials='.$backfill['officials_inserted'].' federation='.$backfill['federation_inserted']);

            return self::SUCCESS;
        }

        $total = 0;
        $officialsInserted = 0;
        $federationInserted = 0;

        foreach ($tenantIds as $tenantId) {
            try {
                $processed = $processor->processForTenant($tenantId);
                $backfill = $backfillService->backfill($tenantId);
                $total += $processed;
                $officialsInserted += $backfill['officials_inserted'];
                $federationInserted += $backfill['federation_inserted'];
                $this->line("Tenant {$tenantId}: archived {$processed} term(s); backfill officials={$backfill['officials_inserted']} federation={$backfill['federation_inserted']}.");
            } catch (Throwable $e) {
                report($e);
                $this->error("Tenant {$tenantId}: ".$e->getMessage());
            }
        }

        if ($total > 0 || $officialsInserted > 0 || $federationInserted > 0) {
            app(SkFedPageCache::class)->bumpMany([
                'accounts.federation',
                'accounts.officials',
                'archive.sk_officials_records',
                'archive.sk_federation_records',
            ]);
        }

        $this->info('Archived '.$total.' expired term record(s).');
        $this->info('INSERT-only backfill: officials='.$officialsInserted.' federation='.$federationInserted);

        return self::SUCCESS;
    }
}
