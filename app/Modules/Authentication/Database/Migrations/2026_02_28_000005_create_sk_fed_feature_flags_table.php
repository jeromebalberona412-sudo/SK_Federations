<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_fed_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('flag_key', 190)->unique();
            $table->boolean('enabled')->default(false);
            $table->string('description')->nullable();
            $table->unsignedTinyInteger('rollout_percentage')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::table('sk_fed_feature_flags')->insert([
            [
                'flag_key' => 'features.device_verification',
                'enabled' => true,
                'description' => 'Enable trusted-device challenge flow for SK FED.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'flag_key' => 'features.login_alert_notifications',
                'enabled' => true,
                'description' => 'Send login alerts for unusual sign-ins.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'flag_key' => 'features.suspicious_login_detection',
                'enabled' => true,
                'description' => 'Detect suspicious login patterns.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_fed_feature_flags');
    }
};
