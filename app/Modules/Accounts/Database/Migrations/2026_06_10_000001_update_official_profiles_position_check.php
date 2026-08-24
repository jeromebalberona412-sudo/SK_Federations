<?php

use App\Modules\Accounts\Models\OfficialProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $positions = OfficialProfile::allowedPositions();
        $quoted = implode(', ', array_map(
            static fn (string $position): string => DB::getPdo()->quote($position),
            $positions,
        ));

        DB::statement('ALTER TABLE official_profiles DROP CONSTRAINT IF EXISTS official_profiles_position_check');
        DB::statement("ALTER TABLE official_profiles ADD CONSTRAINT official_profiles_position_check CHECK (position IN ({$quoted}))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $legacyPositions = ['Chairman', 'Councilor', 'Kagawad', 'Treasurer', 'Secretary', 'Auditor', 'PIO'];
        $quoted = implode(', ', array_map(
            static fn (string $position): string => DB::getPdo()->quote($position),
            $legacyPositions,
        ));

        DB::statement('ALTER TABLE official_profiles DROP CONSTRAINT IF EXISTS official_profiles_position_check');
        DB::statement("ALTER TABLE official_profiles ADD CONSTRAINT official_profiles_position_check CHECK (position IN ({$quoted}))");
    }
};
