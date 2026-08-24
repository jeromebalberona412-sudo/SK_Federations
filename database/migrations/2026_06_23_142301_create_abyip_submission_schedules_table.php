<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('abyip_submission_schedules')) {
            Schema::create('abyip_submission_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->unsignedSmallInteger('fiscal_year');
                $table->string('title')->default('ABYIP Submission');
                $table->date('date_start');
                $table->date('deadline');
                $table->date('original_deadline');
                $table->string('status', 40)->default('upcoming');
                $table->boolean('allow_late_extension')->default(false);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'fiscal_year']);
                $table->index(['status', 'deadline']);
            });
        }

        if (! Schema::hasTable('abyip_submission_schedule_histories')) {
            Schema::create('abyip_submission_schedule_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('schedule_id')->constrained('abyip_submission_schedules')->cascadeOnDelete();
                $table->string('action', 40);
                $table->date('old_deadline')->nullable();
                $table->date('new_deadline')->nullable();
                $table->date('old_date_start')->nullable();
                $table->date('new_date_start')->nullable();
                $table->text('reason')->nullable();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('abyip_submission_schedule_histories');
        Schema::dropIfExists('abyip_submission_schedules');
    }
};
