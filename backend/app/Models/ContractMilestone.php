<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractMilestone extends Model
{
    use HasFactory;

    protected $fillable = ['contract_id', 'title', 'description', 'amount', 'platform_fee_basis_points', 'client_fee_amount', 'client_total_amount', 'due_date', 'status', 'funding_status', 'submitted_at', 'approved_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function paymentRecords()
    {
        return $this->hasMany(MarketplacePaymentRecord::class, 'contract_milestone_id');
    }

    public function submissions()
    {
        return $this->hasMany(MilestoneSubmission::class)->orderByDesc('version');
    }
}
