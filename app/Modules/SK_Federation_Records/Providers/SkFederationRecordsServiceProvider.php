<?php

namespace App\Modules\SK_Federation_Records\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SkFederationRecordsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Views', 'SK_Federation_Records');

        if (file_exists(__DIR__ . '/../Routes/sk_federation_records.php')) {
            Route::middleware('web')
                ->group(__DIR__ . '/../Routes/sk_federation_records.php');
        }
    }
}
