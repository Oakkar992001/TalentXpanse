<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalCreditTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'proposal_id', 'type', 'amount', 'balance_after', 'description'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }
}
