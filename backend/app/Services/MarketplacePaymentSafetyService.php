<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\User;

class MarketplacePaymentSafetyService
{
    public function placeHold(Contract $contract, ?User $handledBy, string $note): Contract
    {
        if ($contract->payment_hold_status === 'on_hold') {
            return $contract;
        }

        $contract->update([
            'payment_hold_status' => 'on_hold',
            'payment_hold_note' => $note,
            'payment_hold_at' => now(),
            'payment_hold_by' => $handledBy?->id,
        ]);

        return $contract->fresh();
    }

    public function clearHold(Contract $contract, User $handledBy, string $note): Contract
    {
        $contract->update([
            'payment_hold_status' => 'clear',
            'payment_hold_note' => $note,
            'payment_hold_at' => null,
            'payment_hold_by' => $handledBy->id,
        ]);

        return $contract->fresh();
    }
}
