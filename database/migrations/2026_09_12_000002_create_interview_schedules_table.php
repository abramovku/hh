<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Leads (ПК_Лид) waiting for the reminder call on the interview day and the
     * feedback call the day after (ТЗ раздел 6).
     */
    public function up(): void
    {
        Schema::create('interview_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('candidate_id')->index();
            $table->unsignedBigInteger('vacancy_id')->nullable();
            $table->string('phone', 32);
            $table->date('interview_date')->index();
            $table->dateTime('interview_at')->nullable();
            $table->string('customer_login')->nullable();
            $table->string('stage', 32)->index();
            $table->string('skip_reason')->nullable();
            $table->string('reminder_autocall_id')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->date('feedback_date')->nullable()->index();
            $table->string('feedback_autocall_id')->nullable();
            $table->timestamp('feedback_sent_at')->nullable();
            $table->json('results')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'interview_date'], 'interview_schedules_candidate_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_schedules');
    }
};
