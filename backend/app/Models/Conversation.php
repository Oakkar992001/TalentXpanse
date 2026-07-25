<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['job_id', 'proposal_id', 'client_id', 'freelancer_id', 'type', 'client_last_read_at', 'freelancer_last_read_at', 'last_message_at'];

    protected function casts(): array
    {
        return ['client_last_read_at' => 'datetime', 'freelancer_last_read_at' => 'datetime', 'last_message_at' => 'datetime'];
    }

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function freelancer()
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function messages()
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function involves(User $user): bool
    {
        return in_array($user->id, [$this->client_id, $this->freelancer_id], true);
    }
}
