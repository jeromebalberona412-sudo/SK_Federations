<?php

use App\Models\SkFederationCalendarNote;
use Illuminate\Database\Eloquent\SoftDeletes;

it('uses soft deletes on sk federations calendar notes', function () {
    $traits = class_uses_recursive(SkFederationCalendarNote::class);

    expect($traits)->toContain(SoftDeletes::class);
    expect((new SkFederationCalendarNote)->getTable())->toBe('sk_federations_calendar');
});
