<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_federations_calendar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('note_date');
            $table->string('title', 255);
            $table->text('content');
            $table->timestamps();

            $table->unique('note_date', 'sk_federations_calendar_note_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_federations_calendar');
    }
};
