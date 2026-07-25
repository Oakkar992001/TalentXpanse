<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationEvent extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'contract_id', 'type', 'body'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
