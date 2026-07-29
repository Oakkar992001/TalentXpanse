<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_offers', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('status');
            $table->index(['status', 'expires_at']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->timestamp('freelancer_completion_requested_at')->nullable()->after('completed_at');
            $table->text('freelancer_completion_note')->nullable()->after('freelancer_completion_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['freelancer_completion_requested_at', 'freelancer_completion_note']);
        });

        Schema::table('proposal_offers', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
