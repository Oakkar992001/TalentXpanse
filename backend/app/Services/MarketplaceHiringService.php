<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Proposal;
use App\Models\ProposalOffer;
use Illuminate\Support\Facades\DB;

class MarketplaceHiringService
{
    public function startContract(Proposal $proposal, MarketplaceNotificationService $notifications, MarketplacePaymentService $payments, ?ProposalOffer $offer = null): Contract
    {
        return DB::transaction(function () use ($proposal, $notifications, $payments, $offer) {
            $selected = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $job = $selected->job()->lockForUpdate()->firstOrFail();
            abort_unless($job->status === 'open', 422, 'This job already has a hiring decision.');

            $lockedOffer = $offer ? ProposalOffer::query()->lockForUpdate()->findOrFail($offer->id) : null;
            if ($lockedOffer) {
                abort_unless($lockedOffer->proposal_id === $selected->id && $lockedOffer->status === 'pending', 422, 'This offer is no longer awaiting a response.');
                abort_if($lockedOffer->expires_at?->isPast(), 422, 'This offer has expired and can no longer be accepted.');
                abort_unless($selected->status === 'offered', 422, 'This proposal is no longer awaiting an offer response.');
            } else {
                abort_unless(in_array($selected->status, ['submitted', 'shortlisted', 'interviewing'], true), 422, 'Only an active proposal can be hired.');
            }

            $job->update(['status' => 'in_progress']);
            $selected->update(['status' => 'hired']);
            $job->proposals()
                ->whereKeyNot($selected->id)
                ->whereIn('status', ['submitted', 'shortlisted', 'interviewing', 'offered'])
                ->update(['status' => 'declined']);
            ProposalOffer::query()
                ->where('proposal_id', '!=', $selected->id)
                ->where('status', 'pending')
                ->whereHas('proposal', fn ($query) => $query->where('job_id', $job->id))
                ->update(['status' => 'withdrawn', 'responded_at' => now()]);

            $conversation = Conversation::updateOrCreate(['proposal_id' => $selected->id], [
                'job_id' => $job->id,
                'client_id' => $job->client_id,
                'freelancer_id' => $selected->freelancer_id,
                'type' => 'project',
            ]);
            $contract = Contract::updateOrCreate(['proposal_id' => $selected->id], [
                'job_id' => $job->id,
                'client_id' => $job->client_id,
                'freelancer_id' => $selected->freelancer_id,
                'title' => $job->title,
                'scope' => $job->description,
                'agreed_amount' => $lockedOffer?->offered_amount ?? $selected->bid_amount,
                'status' => 'active',
                'started_at' => now(),
            ]);

            if ($lockedOffer) {
                $contract->milestones()->delete();
                foreach ($lockedOffer->milestones as $milestone) {
                    $quote = $payments->quote((int) $milestone['amount']);
                    $contract->milestones()->create([
                        'title' => $milestone['title'],
                        'description' => $milestone['description'] ?? null,
                        'amount' => $milestone['amount'],
                        'due_date' => $milestone['due_date'] ?? null,
                        'platform_fee_basis_points' => $quote['platform_fee_basis_points'],
                        'client_fee_amount' => $quote['platform_fee_amount'],
                        'client_total_amount' => $quote['client_total_amount'],
                    ]);
                }
                $lockedOffer->update(['status' => 'accepted', 'responded_by' => $selected->freelancer_id, 'responded_at' => now()]);
            }

            ConversationEvent::firstOrCreate([
                'conversation_id' => $conversation->id,
                'contract_id' => $contract->id,
                'type' => 'contract_started',
            ], ['body' => $lockedOffer ? 'Offer accepted. The contract and agreed milestones are ready.' : 'Contract started. The client can now create delivery milestones.']);
            $conversation->update(['last_message_at' => now()]);
            $notifications->send($selected->freelancer_id, 'proposal_hired', 'You were hired', "You were hired for {$job->title}. Open the project to review the delivery plan.", "/projects/{$contract->id}");

            return $contract;
        });
    }
}
