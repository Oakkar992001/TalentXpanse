<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_escrow_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_payment_record_id');
            $table->foreignId('contract_id');
            $table->foreignId('contract_milestone_id')->nullable();
            $table->string('account', 50);
            $table->string('entry_type', 10);
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('MMK');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['contract_id', 'account'], 'escrow_ledger_contract_account_idx');
            $table->index(['contract_milestone_id', 'created_at'], 'escrow_ledger_milestone_created_idx');
            $table->index(['marketplace_payment_record_id', 'entry_type'], 'escrow_ledger_record_type_idx');
            $table->foreign('marketplace_payment_record_id', 'escrow_ledger_record_fk')->references('id')->on('marketplace_payment_records')->cascadeOnDelete();
            $table->foreign('contract_id', 'escrow_ledger_contract_fk')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('contract_milestone_id', 'escrow_ledger_milestone_fk')->references('id')->on('contract_milestones')->nullOnDelete();
        });

        Schema::create('marketplace_payment_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id');
            $table->foreignId('support_request_id')->unique();
            $table->foreignId('opened_by');
            $table->string('status', 30)->default('open');
            $table->string('resolution', 30)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'status'], 'payment_dispute_contract_status_idx');
            $table->foreign('contract_id', 'payment_dispute_contract_fk')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('support_request_id', 'payment_dispute_support_fk')->references('id')->on('contract_support_requests')->cascadeOnDelete();
            $table->foreign('opened_by', 'payment_dispute_opener_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('resolved_by', 'payment_dispute_resolver_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_payment_disputes');
        Schema::dropIfExists('marketplace_escrow_ledger_entries');
    }
};
