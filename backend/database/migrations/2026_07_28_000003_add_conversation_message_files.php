<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });

        Schema::create('conversation_message_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('storage_path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
            $table->index('conversation_message_id', 'conversation_message_file_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_message_files');
        DB::table('conversation_messages')->whereNull('body')->update(['body' => '']);

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->text('body')->nullable(false)->change();
        });
    }
};
