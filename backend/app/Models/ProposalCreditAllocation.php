<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalCreditAllocation extends Model
{
    use HasFactory;

    protected $fillable = ['proposal_id', 'proposal_credit_grant_id', 'amount'];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function proposalCreditGrant()
    {
        return $this->belongsTo(ProposalCreditGrant::class);
    }
}
