<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::whenTableDoesntHaveIndex('proposal_credit_grants', ['proposal_id'], function (Blueprint $table) {
            $table->index('proposal_id');
        });
        Schema::whenTableHasIndex('proposal_credit_grants', ['proposal_id', 'source'], function (Blueprint $table) {
            $table->dropUnique(['proposal_id', 'source']);
        }, 'unique');

        if (! Schema::hasTable('proposal_credit_allocations')) {
            Schema::create('proposal_credit_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
                $table->foreignId('proposal_credit_grant_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('amount');
                $table->timestamps();
            });
        }
        Schema::whenTableDoesntHaveIndex('proposal_credit_allocations', ['proposal_id', 'proposal_credit_grant_id'], function (Blueprint $table) {
            $table->unique(['proposal_id', 'proposal_credit_grant_id'], 'proposal_credit_allocation_grant_unique');
        }, 'unique');
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_credit_allocations');

        Schema::whenTableDoesntHaveIndex('proposal_credit_grants', ['proposal_id', 'source'], function (Blueprint $table) {
            $table->unique(['proposal_id', 'source']);
        }, 'unique');
    }
};
