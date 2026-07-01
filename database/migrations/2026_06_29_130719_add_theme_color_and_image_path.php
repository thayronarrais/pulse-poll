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
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('theme_color')->nullable()->after('slug');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('theme_color');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
