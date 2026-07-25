<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('project_url')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('freelancer_resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->unsignedInteger('file_size');
            $table->timestamps();
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->string('resume_path')->nullable()->after('credit_cost');
            $table->string('resume_name')->nullable()->after('resume_path');
        });

        Schema::create('proposal_work_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('project_url')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_work_samples');
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['resume_path', 'resume_name']);
        });
        Schema::dropIfExists('freelancer_resumes');
        Schema::dropIfExists('portfolio_items');
    }
};
