<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishModuleAssetsCommand extends Command
{
    protected $signature = 'modules:publish-assets';

    protected $description = 'Copy module CSS/JS/images into public/modules so Hostinger can serve them as static files';

    /**
     * URL slug => source directory under app/Modules (must match blade url('/modules/{slug}/...')).
     *
     * @var array<string, string>
     */
    private const MODULE_ASSET_MAP = [
        'layout' => 'Layout/assets',
        'dashboard' => 'Dashboard/assets',
        'profile' => 'Profile/assets',
        'accounts' => 'Accounts/assets',
        'audit-log' => 'AuditLog/assets',
        'deleted-posts' => 'Deleted_Posts/assets',
        'deleted-sk-officials' => 'Deleted_SK_Officials/assets',
        'rejected-abyip' => 'Rejected_Abyip/assets',
        'sk-officials-records' => 'SK_Officials_Records/assets',
        'sk-federation-records' => 'SK_Federation_Records/assets',
        'barangay-monitoring' => 'BarangayMonitoring/assets',
        'barangay-logos' => 'BarangayLogos/assets',
        'barangay-abyip' => 'Barangay_ABYIP/Assets',
        'kabataan-monitoring' => 'KabataanMonitoring/assets',
        'calendar' => 'Calendar/assets',
        'community-feed' => 'CommunityFeed/assets',
        'notifications' => 'Notifications/assets',
        'reports' => 'Reports/assets',
        'turnover' => 'Turnover/assets',
        'authentication' => 'Authentication/assets',
    ];

    public function handle(): int
    {
        $copied = 0;
        $missing = 0;

        foreach (self::MODULE_ASSET_MAP as $slug => $relativeSource) {
            $source = app_path('Modules/'.$relativeSource);
            $destination = public_path('modules/'.$slug);

            if (! is_dir($source)) {
                $this->warn("Missing source: {$relativeSource}");
                $missing++;

                continue;
            }

            if (is_dir($destination)) {
                File::deleteDirectory($destination);
            }

            File::copyDirectory($source, $destination);
            $this->line("Published: modules/{$slug}");
            $copied++;
        }

        $this->newLine();
        $this->info("Done. Published {$copied} module(s). Missing sources: {$missing}.");

        if ($copied > 0) {
            $this->comment('Upload the public/modules folder to Hostinger (or run this command on the server).');
        }

        return $missing > 0 && $copied === 0 ? self::FAILURE : self::SUCCESS;
    }
}
