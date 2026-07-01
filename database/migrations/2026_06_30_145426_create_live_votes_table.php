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
        Schema::create('live_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_question_id')->constrained()->cascadeOnDelete();
            $table->string('device_token', 64);
            $table->string('choice');
            $table->timestamps();
            $table->unique(['live_question_id', 'device_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_votes');
    }
};
