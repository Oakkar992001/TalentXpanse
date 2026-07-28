<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ConversationMessageService
{
    public function create(Conversation $conversation, User $sender, ?string $body, array $files = []): ConversationMessage
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($conversation, $sender, $body, $files, &$storedPaths) {
                $message = $conversation->messages()->create([
                    'sender_id' => $sender->id,
                    'body' => filled($body) ? trim($body) : null,
                ]);

                foreach ($files as $file) {
                    /** @var UploadedFile $file */
                    $extension = $file->extension() ?: 'file';
                    $path = "conversation-files/{$conversation->id}/".Str::uuid().".{$extension}";
                    Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));
                    $storedPaths[] = $path;
                    $message->files()->create([
                        'uploaded_by' => $sender->id,
                        'original_name' => $file->getClientOriginalName(),
                        'storage_path' => $path,
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'file_size' => $file->getSize(),
                    ]);
                }

                $conversation->update(['last_message_at' => now()]);

                return $message->load(['sender', 'files']);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }
}
