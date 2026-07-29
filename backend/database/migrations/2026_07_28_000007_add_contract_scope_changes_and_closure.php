<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('closed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->text('close_reason')->nullable()->after('closed_by');
            $table->timestamp('closed_at')->nullable()->after('close_reason');
        });

        Schema::create('contract_scope_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description');
            $table->integer('amount_delta')->default(0);
            $table->date('proposed_due_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('response_note')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['contract_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_scope_change_requests');
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['close_reason', 'closed_at']);
        });
    }
};
