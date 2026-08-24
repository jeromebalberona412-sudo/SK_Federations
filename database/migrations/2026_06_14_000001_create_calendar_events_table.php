<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calendar_events')) {
            return;
        }

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('title');
            $table->text('description');
            $table->string('task_type', 30);
            $table->string('status', 20)->default('Pending');
            $table->string('target_audience', 50)->default('SK Fed');
            $table->timestamps();

            $table->index(['event_date', 'target_audience']);
            $table->index(['barangay_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
