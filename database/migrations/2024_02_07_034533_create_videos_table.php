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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->text('video_path');
            $table->text('video_name');
            $table->tinyInteger('current_progress')->default(0); //Current progress, out of 100
            $table->tinyInteger('is_processing')->default(0); // If video is being processed right now
            $table->tinyInteger('is_translated')->default(0); //If video is already translated
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
