<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('contact_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('response_id')->nullable();
            $table->foreign('response_id')->references('id')->on('responses')->nullOnDelete();
            $table->string('type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_events');
    }
};
