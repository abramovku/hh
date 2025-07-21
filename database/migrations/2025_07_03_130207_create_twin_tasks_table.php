<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('twin_tasks', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('chat_id');
            $table->string('job_id');
            $table->unsignedBigInteger('candidate_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twin_tasks');
    }
};
