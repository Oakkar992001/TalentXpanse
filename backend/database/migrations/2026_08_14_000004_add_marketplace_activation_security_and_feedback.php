<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A failed migration can have completed an earlier DDL statement in MySQL.
        // These guards make a retry safe without altering existing marketplace data.
        if (! Schema::hasColumn('users', 'onboarding_rewarded_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('onboarding_rewarded_at')->nullable()->after('privacy_accepted_at'));
        }
        if (! Schema::hasColumn('users', 'two_factor_secret')) {
            Schema::table('users', fn (Blueprint $table) => $table->text('two_factor_secret')->nullable()->after('onboarding_rewarded_at'));
        }
        if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
            Schema::table('users', fn (Blueprint $table) => $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret'));
        }
        if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes'));
        }

        if (! Schema::hasTable('marketplace_feedback')) {
            Schema::create('marketplace_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('area', 50);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('message');
            $table->string('page_url', 500)->nullable();
            $table->string('status', 30)->default('new');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('marketplace_product_events')) {
            Schema::create('marketplace_product_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 80);
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['event', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('marketplace_reliability_appeals')) {
            Schema::create('marketplace_reliability_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('marketplace_reliability_event_id');
            $table->text('reason');
            $table->string('status', 30)->default('open');
            $table->foreignId('reviewed_by')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            // MariaDB/MySQL limits identifiers to 64 characters; use concise explicit names.
            $table->foreign('user_id', 'mra_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('marketplace_reliability_event_id', 'mra_event_fk')->references('id')->on('marketplace_reliability_events')->cascadeOnDelete();
            $table->foreign('reviewed_by', 'mra_reviewer_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['user_id', 'marketplace_reliability_event_id'], 'marketplace_appeals_user_event_unique');
            $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_reliability_appeals');
        Schema::dropIfExists('marketplace_product_events');
        Schema::dropIfExists('marketplace_feedback');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_rewarded_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
