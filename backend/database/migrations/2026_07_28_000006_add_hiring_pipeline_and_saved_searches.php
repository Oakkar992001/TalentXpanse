<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->text('client_note')->nullable()->after('status');
            $table->string('decline_reason', 180)->nullable()->after('client_note');
            $table->timestamp('interview_at')->nullable()->after('decline_reason');
        });

        Schema::create('marketplace_freelancer_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('marketplace_jobs')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['job_id', 'freelancer_id']);
            $table->index(['freelancer_id', 'status']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('marketplace_saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('scope', 20);
            $table->json('filters');
            $table->boolean('alerts_enabled')->default(true);
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_saved_searches');
        Schema::dropIfExists('marketplace_freelancer_invites');

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['client_note', 'decline_reason', 'interview_at']);
        });
    }
};
