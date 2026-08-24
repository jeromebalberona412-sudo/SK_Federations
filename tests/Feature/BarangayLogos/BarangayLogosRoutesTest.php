<?php

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

it('registers barangay logos routes in sk federation', function () {
    expect(Route::has('barangay-logos.index'))->toBeTrue();
    expect(Route::has('barangay-logos.upload'))->toBeTrue();
    expect(Route::has('barangay-logos.delete'))->toBeTrue();
});

it('redirects guests away from barangay logos page', function () {
    get('/barangay-logos')->assertRedirect('/login');
});
