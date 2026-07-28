<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractMilestone;

class MarketplacePaymentService
{
    public function policy(): array
    {
        $feeBasisPoints = config('marketplace_payments.platform_fee_basis_points');

        return [
            'currency' => config('marketplace_payments.currency'),
            'platform_fee_basis_points' => $feeBasisPoints,
            'platform_fee_percent' => $feeBasisPoints / 100,
            'payments_enabled' => config('marketplace_payments.enabled'),
        ];
    }

    public function quote(int $milestoneAmount, ?int $feeBasisPoints = null): array
    {
        $policy = $this->policy();
        $feeBasisPoints ??= $policy['platform_fee_basis_points'];
        $platformFee = (int) ceil($milestoneAmount * $feeBasisPoints / 10000);

        return [
            'currency' => $policy['currency'],
            'milestone_amount' => $milestoneAmount,
            'platform_fee_basis_points' => $feeBasisPoints,
            'platform_fee_percent' => $feeBasisPoints / 100,
            'platform_fee_amount' => $platformFee,
            'client_total_amount' => $milestoneAmount + $platformFee,
            'freelancer_payout_amount' => $milestoneAmount,
            'payments_enabled' => $policy['payments_enabled'],
        ];
    }

    public function summary(ContractMilestone $milestone): array
    {
        $quote = $this->quote($milestone->amount, $milestone->platform_fee_basis_points);

        return [
            ...$quote,
            'platform_fee_amount' => $milestone->client_fee_amount ?? $quote['platform_fee_amount'],
            'client_total_amount' => $milestone->client_total_amount ?? $quote['client_total_amount'],
            'funding_status' => $milestone->funding_status,
        ];
    }

    public function safety(Contract $contract): array
    {
        $isOnHold = $contract->payment_hold_status === 'on_hold';

        return [
            'payments_enabled' => config('marketplace_payments.enabled'),
            'payment_hold_status' => $contract->payment_hold_status,
            'payment_hold_note' => $contract->payment_hold_note,
            'payment_hold_at' => $contract->payment_hold_at,
            'release_allowed' => config('marketplace_payments.enabled') && ! $isOnHold,
            'status_message' => $isOnHold
                ? 'Payment safety hold is active. No release can happen until TalentXpanse resolves it.'
                : (config('marketplace_payments.enabled') ? 'Payment processing will be available when a provider is configured.' : 'Payment setup is not available yet.'),
        ];
    }
}
