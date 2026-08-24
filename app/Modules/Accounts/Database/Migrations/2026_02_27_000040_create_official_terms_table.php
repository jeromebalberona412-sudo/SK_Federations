<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_profile_id')->constrained('official_profiles')->cascadeOnDelete();
            $table->date('term_start');
            $table->date('term_end');
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'EXPIRED', 'REPLACED'])->default('ACTIVE');
            $table->timestamps();

            $table->index(['official_profile_id', 'term_end']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_terms');
    }
};
