<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name', 100)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->enum('position', ['Chairman', 'Councilor', 'Kagawad', 'Treasurer', 'Secretary', 'Auditor', 'PIO']);
            $table->string('municipality')->default('Santa Cruz');
            $table->string('province')->default('Laguna');
            $table->string('region')->default('IV-A CALABARZON');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_profiles');
    }
};
