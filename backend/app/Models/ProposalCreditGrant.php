<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalCreditGrant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'proposal_id',
        'source',
        'initial_amount',
        'remaining_amount',
        'granted_at',
        'expires_at',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }
}
