<?php

namespace App\Console\Commands;

use App\Modules\Authentication\Services\BootstrapSkFedAdminService;
use Illuminate\Console\Command;

class EnsureBootstrapSkFedAdminCommand extends Command
{
    protected $signature = 'skfed:ensure-bootstrap-admin
                            {--reset-password : Reset the bootstrap password to the default temporary value}';

    protected $description = 'Ensure the SK Federation bootstrap administrator account exists (skoneportal@gmail.com)';

    public function handle(BootstrapSkFedAdminService $bootstrapSkFedAdminService): int
    {
        $user = $bootstrapSkFedAdminService->ensure(
            resetPassword: (bool) $this->option('reset-password'),
        );

        $this->info('Bootstrap SK Federation admin is ready.');
        $this->line('Email: '.BootstrapSkFedAdminService::BOOTSTRAP_EMAIL);
        $this->line('Role: '.$user->role);
        $this->line('Tenant ID: '.(string) $user->tenant_id);

        if ($this->option('reset-password')) {
            $this->warn('Temporary password: '.BootstrapSkFedAdminService::DEFAULT_PASSWORD);
            $this->warn('Change it after login or use Forgot Password.');
        }

        return self::SUCCESS;
    }
}
