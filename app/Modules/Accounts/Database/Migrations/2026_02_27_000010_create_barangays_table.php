<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('municipality')->default('Santa Cruz');
            $table->string('province')->default('Laguna');
            $table->string('region')->default('IV-A CALABARZON');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangays');
    }
};
