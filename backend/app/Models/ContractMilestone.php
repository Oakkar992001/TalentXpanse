<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractMilestone extends Model
{
    use HasFactory;

    protected $fillable = ['contract_id', 'title', 'description', 'amount', 'due_date', 'status', 'submitted_at', 'approved_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
