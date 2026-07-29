<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\ContractSupportRequest;
use App\Models\MarketplacePaymentDispute;
use App\Models\MarketplacePaymentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceEscrowService
{
    public function __construct(private MarketplacePaymentService $payments)
    {
    }

    public function recordFunding(ContractMilestone $milestone, string $provider, string $providerReference): MarketplacePaymentRecord
    {
        $this->assertGatewayReady($provider);

        return DB::transaction(function () use ($milestone, $provider, $providerReference) {
            [$lockedMilestone, $contract] = $this->lockedMilestone($milestone);
            $existing = $this->existingRecord($provider, $providerReference, 'funding', $lockedMilestone);
            if ($existing) {
                return $existing;
            }

            abort_unless($contract->status === 'active', 422, 'Only active projects can receive escrow funding.');
            abort_if($contract->payment_hold_status === 'on_hold', 422, 'Funding is paused while a payment safety hold is active.');
            abort_unless(in_array($lockedMilestone->funding_status, ['not_configured', 'awaiting_funding'], true), 422, 'This milestone already has a payment outcome.');

            $quote = $this->payments->summary($lockedMilestone);
            $record = $this->record($lockedMilestone, $contract, 'funding', $provider, $providerReference, $quote);
            $this->entries($record, [
                ['account' => 'escrow_cash', 'entry_type' => 'debit', 'amount' => $quote['client_total_amount']],
                ['account' => 'freelancer_payable', 'entry_type' => 'credit', 'amount' => $quote['freelancer_payout_amount']],
                ['account' => 'platform_fee_deferred', 'entry_type' => 'credit', 'amount' => $quote['platform_fee_amount']],
            ]);
            $lockedMilestone->update(['funding_status' => 'funded']);

            return $record->load('ledgerEntries');
        });
    }

    public function recordRelease(ContractMilestone $milestone, string $provider, string $providerReference): MarketplacePaymentRecord
    {
        $this->assertGatewayReady($provider);

        return DB::transaction(function () use ($milestone, $provider, $providerReference) {
            [$lockedMilestone, $contract] = $this->lockedMilestone($milestone);
            $existing = $this->existingRecord($provider, $providerReference, 'release', $lockedMilestone);
            if ($existing) {
                return $existing;
            }

            abort_unless($contract->status === 'active', 422, 'Only active projects can release escrow funds.');
            abort_if($contract->payment_hold_status === 'on_hold', 422, 'Release is blocked while a payment safety hold is active.');
            abort_unless($lockedMilestone->status === 'approved', 422, 'Only approved milestones can release escrow funds.');
            abort_unless($lockedMilestone->funding_status === 'funded', 422, 'This milestone is not funded and ready for release.');

            $quote = $this->payments->summary($lockedMilestone);
            $record = $this->record($lockedMilestone, $contract, 'release', $provider, $providerReference, $quote);
            $this->entries($record, [
                ['account' => 'freelancer_payable', 'entry_type' => 'debit', 'amount' => $quote['freelancer_payout_amount']],
                ['account' => 'platform_fee_deferred', 'entry_type' => 'debit', 'amount' => $quote['platform_fee_amount']],
                ['account' => 'escrow_cash', 'entry_type' => 'credit', 'amount' => $quote['freelancer_payout_amount']],
                ['account' => 'platform_fee_revenue', 'entry_type' => 'credit', 'amount' => $quote['platform_fee_amount']],
            ]);
            $lockedMilestone->update(['funding_status' => 'released']);

            return $record->load('ledgerEntries');
        });
    }

    public function recordRefund(ContractMilestone $milestone, string $provider, string $providerReference): MarketplacePaymentRecord
    {
        $this->assertGatewayReady($provider);

        return DB::transaction(function () use ($milestone, $provider, $providerReference) {
            [$lockedMilestone, $contract] = $this->lockedMilestone($milestone);
            $existing = $this->existingRecord($provider, $providerReference, 'refund', $lockedMilestone);
            if ($existing) {
                return $existing;
            }

            abort_if($contract->payment_hold_status !== 'on_hold', 422, 'Refunds require an active payment dispute hold.');
            abort_unless($lockedMilestone->funding_status === 'disputed', 422, 'Only disputed funded milestones can be refunded.');

            $quote = $this->payments->summary($lockedMilestone);
            $record = $this->record($lockedMilestone, $contract, 'refund', $provider, $providerReference, $quote);
            $this->entries($record, [
                ['account' => 'freelancer_payable', 'entry_type' => 'debit', 'amount' => $quote['freelancer_payout_amount']],
                ['account' => 'platform_fee_deferred', 'entry_type' => 'debit', 'amount' => $quote['platform_fee_amount']],
                ['account' => 'escrow_cash', 'entry_type' => 'credit', 'amount' => $quote['client_total_amount']],
            ]);
            $lockedMilestone->update(['funding_status' => 'refunded']);

            return $record->load('ledgerEntries');
        });
    }

    public function openDispute(Contract $contract, ContractSupportRequest $supportRequest): ?MarketplacePaymentDispute
    {
        return DB::transaction(function () use ($contract, $supportRequest) {
            $lockedContract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $fundedMilestones = $lockedContract->milestones()->where('funding_status', 'funded')->lockForUpdate()->get();
            if ($fundedMilestones->isEmpty()) {
                return null;
            }

            $dispute = MarketplacePaymentDispute::firstOrCreate(
                ['support_request_id' => $supportRequest->id],
                [
                    'contract_id' => $lockedContract->id,
                    'opened_by' => $supportRequest->opened_by,
                    'status' => 'open',
                ],
            );
            $fundedMilestones->each->update(['funding_status' => 'disputed']);

            return $dispute;
        });
    }

    public function resumeDisputedFunds(Contract $contract, User $administrator, string $note): void
    {
        DB::transaction(function () use ($contract, $administrator, $note) {
            $lockedContract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $disputedMilestones = $lockedContract->milestones()->where('funding_status', 'disputed')->lockForUpdate()->count();
            $refundedMilestones = $lockedContract->milestones()->where('funding_status', 'refunded')->count();
            $resolution = $refundedMilestones > 0
                ? ($disputedMilestones > 0 ? 'partial_refund' : 'refund')
                : 'resume';
            $lockedContract->milestones()->where('funding_status', 'disputed')->update(['funding_status' => 'funded']);
            MarketplacePaymentDispute::query()
                ->where('contract_id', $lockedContract->id)
                ->where('status', 'open')
                ->update([
                    'status' => 'resolved',
                    'resolution' => $resolution,
                    'resolution_note' => $note,
                    'resolved_by' => $administrator->id,
                    'resolved_at' => now(),
                ]);
        });
    }

    private function lockedMilestone(ContractMilestone $milestone): array
    {
        $lockedMilestone = ContractMilestone::query()->lockForUpdate()->findOrFail($milestone->id);
        $contract = Contract::query()->lockForUpdate()->findOrFail($lockedMilestone->contract_id);

        return [$lockedMilestone, $contract];
    }

    private function existingRecord(string $provider, string $providerReference, string $type, ContractMilestone $milestone): ?MarketplacePaymentRecord
    {
        $existing = MarketplacePaymentRecord::query()
            ->where('provider', $provider)
            ->where('provider_reference', $providerReference)
            ->lockForUpdate()
            ->first();

        abort_if($existing && ($existing->type !== $type || $existing->contract_milestone_id !== $milestone->id), 422, 'This provider reference was already used for another payment event.');

        return $existing?->load('ledgerEntries');
    }

    private function record(ContractMilestone $milestone, Contract $contract, string $type, string $provider, string $providerReference, array $quote): MarketplacePaymentRecord
    {
        return MarketplacePaymentRecord::create([
            'contract_id' => $contract->id,
            'contract_milestone_id' => $milestone->id,
            'client_id' => $contract->client_id,
            'freelancer_id' => $contract->freelancer_id,
            'type' => $type,
            'status' => 'settled',
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'currency' => $quote['currency'],
            'milestone_amount' => $quote['milestone_amount'],
            'platform_fee_amount' => $quote['platform_fee_amount'],
            'provider_fee_amount' => 0,
            'client_total_amount' => $quote['client_total_amount'],
            'freelancer_payout_amount' => $quote['freelancer_payout_amount'],
            'idempotency_key' => (string) Str::uuid(),
            'processed_at' => now(),
        ]);
    }

    private function entries(MarketplacePaymentRecord $record, array $entries): void
    {
        $record->ledgerEntries()->createMany(array_map(fn (array $entry) => $entry + [
            'contract_id' => $record->contract_id,
            'contract_milestone_id' => $record->contract_milestone_id,
            'currency' => $record->currency,
        ], $entries));
    }

    private function assertGatewayReady(string $provider): void
    {
        abort_unless($this->payments->gatewayConfigured() && $provider === config('marketplace_payments.provider'), 422, 'A configured payment gateway is required before escrow can be settled.');
    }
}
