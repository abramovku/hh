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
        Schema::create('hh_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('response_id')->index();
            $table->string('key');
            $table->text('value');

            $table->foreign('response_id')
                ->references('id')
                ->on('responses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hh_meta');
    }
};
