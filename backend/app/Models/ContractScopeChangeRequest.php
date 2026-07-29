<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractScopeChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = ['contract_id', 'requested_by', 'title', 'description', 'amount_delta', 'proposed_due_date', 'status', 'response_note', 'responded_by', 'responded_at'];

    protected function casts(): array
    {
        return ['proposed_due_date' => 'date', 'responded_at' => 'datetime'];
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
