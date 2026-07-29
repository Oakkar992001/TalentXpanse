<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'proposal_id',
        'client_id',
        'freelancer_id',
        'offered_amount',
        'delivery_days',
        'start_date',
        'message',
        'milestones',
        'status',
        'expires_at',
        'responded_by',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'milestones' => 'array',
            'start_date' => 'date',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
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

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
