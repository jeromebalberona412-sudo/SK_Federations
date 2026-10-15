<?php

use App\Database\CachingPostgresBuilder;

it('checks a table once when the same lookup is repeated', function () {
    CachingPostgresBuilder::flush();

    $builder = new class extends CachingPostgresBuilder
    {
        public int $lookups = 0;

        public function __construct() {}

        protected function resolveTableExists(string $table): bool
        {
            $this->lookups++;

            return $table === 'kabataan_registrations';
        }

        protected function cacheKey(string $suffix): string
        {
            return 'test|'.$suffix;
        }

        public function changeSchema(Closure $callback): void
        {
            $this->withFreshSchema($callback);
        }
    };

    expect($builder->hasTable('kabataan_registrations'))->toBeTrue()
        ->and($builder->hasTable('kabataan_registrations'))->toBeTrue()
        ->and($builder->hasTable('users'))->toBeFalse()
        ->and($builder->lookups)->toBe(2);

    $builder->changeSchema(function () use ($builder): void {
        $builder->hasTable('kabataan_registrations');
    });

    expect($builder->hasTable('kabataan_registrations'))->toBeTrue()
        ->and($builder->lookups)->toBe(4);

    CachingPostgresBuilder::flush();
});
