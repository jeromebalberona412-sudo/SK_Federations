<?php

namespace App\Modules\SK_Officials_Records\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SkOfficialsRecordsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Views', 'SK_Officials_Records');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        if (file_exists(__DIR__ . '/../Routes/sk_officials_records.php')) {
            Route::middleware('web')
                ->group(__DIR__ . '/../Routes/sk_officials_records.php');
        }
    }
}
