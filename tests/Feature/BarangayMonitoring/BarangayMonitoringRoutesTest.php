<?php

use App\Modules\BarangayMonitoring\Services\BarangayMonitoringService;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

it('registers barangay monitoring and abyip schedule routes', function () {
    expect(Route::has('barangay-monitoring'))->toBeTrue();
    expect(Route::has('barangay-monitoring.show'))->toBeTrue();
    expect(Route::has('api.barangay-monitoring.schedules'))->toBeTrue();
    expect(Route::has('api.barangay-monitoring.schedules.store'))->toBeTrue();
    expect(Route::has('api.barangay-monitoring.schedules.extend'))->toBeTrue();
});

it('redirects legacy barangay abyip page to monitoring', function () {
    get('/barangay-abyip')->assertRedirect('/barangay-monitoring');
});

it('redirects legacy reports page to barangay monitoring', function () {
    get('/reports')->assertRedirect('/barangay-monitoring');
});

it('redirects guests away from barangay monitoring pages', function () {
    get('/barangay-monitoring')->assertRedirect('/login');
    get('/barangay-monitoring/alipit')->assertRedirect('/login');
});

it('maps barangay slugs to official names', function () {
    $service = app(BarangayMonitoringService::class);

    expect($service->nameFromSlug('alipit'))->toBe('Alipit');
    expect($service->nameFromSlug('brgy-1-poblacion'))->toBe('Poblacion I');
    expect($service->skTermForYear(2025))->toBe('2025-2027');
});
