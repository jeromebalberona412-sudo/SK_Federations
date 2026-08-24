<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'barangay_id')) {
                $table->foreignId('barangay_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('barangays')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'barangay_id')) {
                $table->dropConstrainedForeignId('barangay_id');
            }
        });
    }
};
