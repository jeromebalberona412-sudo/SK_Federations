<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure auth feature flags exist on production even when module migrations
 * were never discovered by Hostinger deploys.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sk_fed_feature_flags')) {
            Schema::create('sk_fed_feature_flags', function (Blueprint $table) {
                $table->id();
                $table->string('flag_key', 190)->unique();
                $table->boolean('enabled')->default(false);
                $table->string('description')->nullable();
                $table->unsignedTinyInteger('rollout_percentage')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        $defaults = [
            [
                'flag_key' => 'features.device_verification',
                'enabled' => true,
                'description' => 'Enable trusted-device challenge flow for SK FED.',
            ],
            [
                'flag_key' => 'features.login_alert_notifications',
                'enabled' => true,
                'description' => 'Send login alerts for unusual sign-ins.',
            ],
            [
                'flag_key' => 'features.suspicious_login_detection',
                'enabled' => true,
                'description' => 'Detect suspicious login patterns.',
            ],
        ];

        foreach ($defaults as $row) {
            $exists = DB::table('sk_fed_feature_flags')
                ->where('flag_key', $row['flag_key'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('sk_fed_feature_flags')->insert([
                'flag_key' => $row['flag_key'],
                'enabled' => $row['enabled'],
                'description' => $row['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally keep the table; production auth depends on it.
    }
};
