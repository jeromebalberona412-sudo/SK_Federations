<?php

namespace Database\Seeders;

use App\Modules\Authentication\Services\BootstrapSkFedAdminService;
use Illuminate\Database\Seeder;

class DefaultSkFedAdminSeeder extends Seeder
{
    public function run(): void
    {
        app(BootstrapSkFedAdminService::class)->ensure();
    }
}
