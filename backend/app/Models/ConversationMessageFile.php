<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationMessageFile extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_message_id', 'uploaded_by', 'original_name', 'storage_path', 'mime_type', 'file_size'];

    protected $hidden = ['storage_path'];

    public function message()
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
