<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('identity_verification_status', 30)->default('unverified')->after('status');
            $table->text('identity_verification_note')->nullable()->after('identity_verification_status');
            $table->timestamp('identity_verification_requested_at')->nullable()->after('identity_verification_note');
            $table->timestamp('identity_verified_at')->nullable()->after('identity_verification_requested_at');
            $table->foreignId('identity_verified_by')->nullable()->after('identity_verified_at')->constrained('users')->nullOnDelete();
            $table->index('identity_verification_status');
        });

        Schema::table('client_profiles', function (Blueprint $table) {
            $table->string('company_verification_status', 30)->default('unverified')->after('billing_verified');
            $table->text('company_verification_note')->nullable()->after('company_verification_status');
            $table->timestamp('company_verification_requested_at')->nullable()->after('company_verification_note');
            $table->timestamp('company_verified_at')->nullable()->after('company_verification_requested_at');
            $table->foreignId('company_verified_by')->nullable()->after('company_verified_at')->constrained('users')->nullOnDelete();
            $table->index('company_verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropIndex(['company_verification_status']);
            $table->dropConstrainedForeignId('company_verified_by');
            $table->dropColumn(['company_verification_status', 'company_verification_note', 'company_verification_requested_at', 'company_verified_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['identity_verification_status']);
            $table->dropConstrainedForeignId('identity_verified_by');
            $table->dropColumn(['identity_verification_status', 'identity_verification_note', 'identity_verification_requested_at', 'identity_verified_at']);
        });
    }
};
