<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('payment_hold_status', 20)->default('clear')->after('status');
            $table->text('payment_hold_note')->nullable()->after('payment_hold_status');
            $table->timestamp('payment_hold_at')->nullable()->after('payment_hold_note');
            $table->foreignId('payment_hold_by')->nullable()->after('payment_hold_at')->constrained('users')->nullOnDelete();
            $table->index('payment_hold_status', 'contract_payment_hold_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['payment_hold_by']);
            $table->dropIndex('contract_payment_hold_status_idx');
            $table->dropColumn(['payment_hold_status', 'payment_hold_note', 'payment_hold_at', 'payment_hold_by']);
        });
    }
};
