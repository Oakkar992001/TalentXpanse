<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestone_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_milestone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->text('note')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['contract_milestone_id', 'version'], 'milestone_submission_version_uq');
            $table->index(['contract_milestone_id', 'status'], 'milestone_submission_status_idx');
        });

        Schema::create('milestone_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('storage_path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
            $table->index('milestone_submission_id', 'milestone_submission_file_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestone_submission_files');
        Schema::dropIfExists('milestone_submissions');
    }
};
