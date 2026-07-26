<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_saved_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('marketplace_jobs')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'job_id']);
        });

        Schema::create('marketplace_saved_talent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'freelancer_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_saved_talent');
        Schema::dropIfExists('marketplace_saved_jobs');
    }
};
