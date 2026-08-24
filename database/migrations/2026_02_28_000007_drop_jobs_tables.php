<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
    }

    public function down(): void
    {
        // Intentionally left empty. Jobs tables are not restored automatically.
    }
};
