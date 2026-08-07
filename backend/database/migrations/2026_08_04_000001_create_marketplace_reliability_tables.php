<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('close_reason_code', 40)->nullable()->after('close_reason');
        });

        Schema::create('marketplace_reliability_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->unsignedTinyInteger('score')->default(50);
            $table->string('tier', 30)->default('new');
            $table->string('search_visibility', 30)->default('standard');
            $table->unsignedInteger('completed_projects_count')->default(0);
            $table->unsignedInteger('positive_reviews_count')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->integer('active_penalty_points')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role']);
            $table->index(['role', 'search_visibility']);
        });

        Schema::create('marketplace_reliability_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('event_type', 60);
            $table->integer('points')->default(0);
            $table->string('status', 30)->default('confirmed');
            $table->string('reason_code', 60)->nullable();
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('details')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'role', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['source_type', 'source_id']);
            $table->unique(['user_id', 'role', 'event_type', 'source_type', 'source_id'], 'reliability_events_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_reliability_events');
        Schema::dropIfExists('marketplace_reliability_profiles');

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('close_reason_code');
        });
    }
};
