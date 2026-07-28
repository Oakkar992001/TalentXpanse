<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingFeeBasisPoints = ! Schema::hasColumn('contract_milestones', 'platform_fee_basis_points');
        $missingClientFee = ! Schema::hasColumn('contract_milestones', 'client_fee_amount');
        $missingClientTotal = ! Schema::hasColumn('contract_milestones', 'client_total_amount');
        $missingFundingStatus = ! Schema::hasColumn('contract_milestones', 'funding_status');

        if ($missingFeeBasisPoints || $missingClientFee || $missingClientTotal || $missingFundingStatus) {
            Schema::table('contract_milestones', function (Blueprint $table) use ($missingFeeBasisPoints, $missingClientFee, $missingClientTotal, $missingFundingStatus) {
                if ($missingFeeBasisPoints) {
                    $table->unsignedSmallInteger('platform_fee_basis_points')->default(1000)->after('amount');
                }
                if ($missingClientFee) {
                    $table->unsignedInteger('client_fee_amount')->nullable()->after('platform_fee_basis_points');
                }
                if ($missingClientTotal) {
                    $table->unsignedInteger('client_total_amount')->nullable()->after('client_fee_amount');
                }
                if ($missingFundingStatus) {
                    $table->string('funding_status', 30)->default('not_configured')->after('status');
                }
            });
        }

        if (! Schema::hasTable('marketplace_payment_records')) {
            Schema::create('marketplace_payment_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
                $table->foreignId('contract_milestone_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 30);
                $table->string('status', 30)->default('pending');
                $table->string('provider', 50)->nullable();
                $table->string('provider_reference', 160)->nullable();
                $table->string('currency', 3)->default('MMK');
                $table->unsignedInteger('milestone_amount');
                $table->unsignedInteger('platform_fee_amount')->default(0);
                $table->unsignedInteger('provider_fee_amount')->default(0);
                $table->unsignedInteger('client_total_amount');
                $table->unsignedInteger('freelancer_payout_amount')->default(0);
                $table->uuid('idempotency_key');
                $table->json('metadata')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
                $this->indexes($table);
            });

            return;
        }

        Schema::table('marketplace_payment_records', function (Blueprint $table) {
            $this->indexes($table);
        });
    }

    private function indexes(Blueprint $table): void
    {
        $table->unique('provider_reference', 'mpay_provider_reference_uq');
        $table->unique('idempotency_key', 'mpay_idempotency_key_uq');
        $table->index(['contract_milestone_id', 'type', 'status'], 'mpay_milestone_type_status_idx');
        $table->index(['provider', 'provider_reference'], 'mpay_provider_reference_idx');
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_payment_records');

        Schema::table('contract_milestones', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_basis_points', 'client_fee_amount', 'client_total_amount', 'funding_status']);
        });
    }
};
