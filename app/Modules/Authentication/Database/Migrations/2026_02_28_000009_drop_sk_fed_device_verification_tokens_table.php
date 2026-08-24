<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sk_fed_device_verification_tokens');
    }

    public function down(): void
    {
    }
};
