<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('freelancer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedInteger('hourly_rate')->nullable();
            $table->boolean('availability')->default(true);
            $table->unsignedTinyInteger('profile_completeness')->default(0);
            $table->json('skills')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
        });

        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->text('company_description')->nullable();
            $table->boolean('billing_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('marketplace_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->json('skills')->nullable();
            $table->unsignedInteger('budget_min')->nullable();
            $table->unsignedInteger('budget_max')->nullable();
            $table->string('budget_type')->default('fixed');
            $table->string('duration')->nullable();
            $table->string('experience_level')->default('intermediate');
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('marketplace_jobs')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->text('cover_letter');
            $table->unsignedInteger('bid_amount');
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamps();
            $table->unique(['job_id', 'freelancer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('marketplace_jobs');
        Schema::dropIfExists('client_profiles');
        Schema::dropIfExists('freelancer_profiles');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
