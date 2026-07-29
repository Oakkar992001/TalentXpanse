<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class MarketplaceEscrowLedgerEntry extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'marketplace_payment_record_id',
        'contract_id',
        'contract_milestone_id',
        'account',
        'entry_type',
        'amount',
        'currency',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Escrow ledger entries are immutable. Record a compensating entry instead.'));
        static::deleting(fn () => throw new LogicException('Escrow ledger entries cannot be deleted.'));
    }

    public function paymentRecord()
    {
        return $this->belongsTo(MarketplacePaymentRecord::class, 'marketplace_payment_record_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function milestone()
    {
        return $this->belongsTo(ContractMilestone::class, 'contract_milestone_id');
    }
}
