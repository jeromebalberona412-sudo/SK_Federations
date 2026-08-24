<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('abyip_submission_schedule_histories')) {
            return;
        }

        if (! Schema::hasTable('abyip_submission_schedules')) {
            return;
        }

        $scheduleIdType = Schema::getColumnType('abyip_submission_schedules', 'id');
        $usersIdType = Schema::hasTable('users')
            ? Schema::getColumnType('users', 'id')
            : 'bigint';

        Schema::create('abyip_submission_schedule_histories', function (Blueprint $table) use ($scheduleIdType, $usersIdType) {
            $table->id();

            if (in_array($scheduleIdType, ['uuid', 'guid'], true)) {
                $table->uuid('schedule_id');
            } else {
                $table->unsignedBigInteger('schedule_id');
            }
            $table->foreign('schedule_id')
                ->references('id')
                ->on('abyip_submission_schedules')
                ->cascadeOnDelete();

            $table->string('action', 40);
            $table->date('old_deadline')->nullable();
            $table->date('new_deadline')->nullable();
            $table->date('old_date_start')->nullable();
            $table->date('new_date_start')->nullable();
            $table->text('reason')->nullable();

            if (in_array($usersIdType, ['uuid', 'guid'], true)) {
                $table->uuid('updated_by_user_id')->nullable();
            } else {
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
            }
            $table->foreign('updated_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abyip_submission_schedule_histories');
    }
};
