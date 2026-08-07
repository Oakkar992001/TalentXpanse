<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_credit_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 40);
            $table->unsignedSmallInteger('initial_amount');
            $table->unsignedSmallInteger('remaining_amount');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('reference', 120)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
            $table->index(['user_id', 'source']);
            $table->unique(['proposal_id', 'source']);
        });

        Schema::table('proposal_credit_accounts', function (Blueprint $table) {
            $table->string('membership_tier', 20)->default('free')->after('balance');
            $table->date('membership_expires_at')->nullable()->after('last_monthly_grant_at');
        });

        Schema::table('proposal_credit_transactions', function (Blueprint $table) {
            $table->foreignId('proposal_credit_grant_id')->nullable()->after('proposal_id')->constrained()->nullOnDelete();
        });

        $now = now();
        $expiresAt = $now->copy()->addDays(90);

        DB::table('proposal_credit_accounts')
            ->where('balance', '>', 0)
            ->orderBy('id')
            ->each(function (object $account) use ($now, $expiresAt) {
                DB::table('proposal_credit_grants')->insert([
                    'user_id' => $account->user_id,
                    'source' => 'legacy_transition',
                    'initial_amount' => $account->balance,
                    'remaining_amount' => $account->balance,
                    'granted_at' => $now,
                    'expires_at' => $expiresAt,
                    'reference' => 'Balance preserved during credit-policy migration',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('proposal_credit_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proposal_credit_grant_id');
        });

        Schema::table('proposal_credit_accounts', function (Blueprint $table) {
            $table->dropColumn(['membership_tier', 'membership_expires_at']);
        });

        Schema::dropIfExists('proposal_credit_grants');
    }
};
