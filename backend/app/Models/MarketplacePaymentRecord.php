<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplacePaymentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'contract_milestone_id',
        'client_id',
        'freelancer_id',
        'type',
        'status',
        'provider',
        'provider_reference',
        'currency',
        'milestone_amount',
        'platform_fee_amount',
        'provider_fee_amount',
        'client_total_amount',
        'freelancer_payout_amount',
        'idempotency_key',
        'metadata',
        'processed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'processed_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    protected $hidden = ['metadata'];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function milestone()
    {
        return $this->belongsTo(ContractMilestone::class, 'contract_milestone_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function freelancer()
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }
}
