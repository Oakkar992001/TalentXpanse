<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('offered_amount');
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->date('start_date')->nullable();
            $table->text('message')->nullable();
            $table->json('milestones');
            $table->string('status', 30)->default('pending');
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['proposal_id', 'status']);
            $table->index(['freelancer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_offers');
    }
};
