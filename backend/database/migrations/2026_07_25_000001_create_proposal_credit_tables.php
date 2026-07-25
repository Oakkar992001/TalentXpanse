<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('balance')->default(0);
            $table->date('last_monthly_grant_at')->nullable();
            $table->timestamps();
        });

        Schema::create('proposal_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->smallInteger('amount');
            $table->unsignedSmallInteger('balance_after');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->unsignedTinyInteger('credit_cost')->default(0)->after('delivery_days');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('credit_cost');
        });

        Schema::dropIfExists('proposal_credit_transactions');
        Schema::dropIfExists('proposal_credit_accounts');
    }
};
