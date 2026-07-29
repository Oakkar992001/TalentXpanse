<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplacePaymentDispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'support_request_id',
        'opened_by',
        'status',
        'resolution',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function supportRequest()
    {
        return $this->belongsTo(ContractSupportRequest::class, 'support_request_id');
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
