<?php

namespace App\Console\Commands;

use App\Modules\Archive_Management\Services\FederationPostArchiveService;
use App\Modules\Shared\Models\Announcement;
use Illuminate\Console\Command;

class PurgeArchivedFederationPosts extends Command
{
    protected $signature = 'federation-posts:purge-archived';

    protected $description = 'Permanently delete federation archived posts older than 30 days';

    public function handle(FederationPostArchiveService $archiveService): int
    {
        $cutoff = now()->subDays(FederationPostArchiveService::RETENTION_DAYS);

        $posts = Announcement::query()
            ->archived()
            ->federationWide()
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', $cutoff)
            ->get();

        foreach ($posts as $post) {
            $archiveService->permanentlyDelete($post);
        }

        $this->info('Purged '.$posts->count().' archived federation posts.');

        return self::SUCCESS;
    }
}
