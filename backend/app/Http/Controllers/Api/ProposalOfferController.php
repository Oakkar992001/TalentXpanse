<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\ProposalOffer;
use App\Services\MarketplaceHiringService;
use App\Services\MarketplaceNotificationService;
use App\Services\MarketplaceOfferService;
use App\Services\MarketplacePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProposalOfferController extends Controller
{
    public function store(Request $request, Proposal $proposal, MarketplaceNotificationService $notifications)
    {
        $data = $request->validate([
            'offered_amount' => ['required', 'integer', 'min:1000'],
            'delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'message' => ['nullable', 'string', 'max:3000'],
            'milestones' => ['required', 'array', 'min:1', 'max:10'],
            'milestones.*.title' => ['required', 'string', 'max:180'],
            'milestones.*.description' => ['nullable', 'string', 'max:2000'],
            'milestones.*.amount' => ['required', 'integer', 'min:1000'],
            'milestones.*.due_date' => ['nullable', 'date'],
        ]);
        abort_unless($proposal->job->client_id === $request->user()->id, 403, 'Only the job owner can make an offer.');
        abort_unless($proposal->job->status === 'open', 422, 'This job is no longer accepting hiring decisions.');
        abort_unless(in_array($proposal->status, ['submitted', 'shortlisted', 'interviewing'], true), 422, 'This proposal is no longer available for an offer.');
        abort_unless(collect($data['milestones'])->sum('amount') === $data['offered_amount'], 422, 'Milestone amounts must equal the total offer amount.');

        $offer = DB::transaction(function () use ($proposal, $request, $data) {
            $lockedProposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless(in_array($lockedProposal->status, ['submitted', 'shortlisted', 'interviewing'], true), 422, 'This proposal is no longer available for an offer.');
            $lockedProposal->offers()->where('status', 'pending')->update(['status' => 'withdrawn', 'responded_at' => now()]);
            $lockedProposal->update(['status' => 'offered']);

            return $lockedProposal->offers()->create($data + [
                'client_id' => $request->user()->id,
                'freelancer_id' => $lockedProposal->freelancer_id,
                'expires_at' => now()->addDays(config('marketplace_offers.expiry_days')),
            ]);
        });
        $notifications->send($offer->freelancer_id, 'proposal_offer_received', 'New offer received', "You received an offer for {$proposal->job->title}. Review the terms before accepting.", '/work?role=freelancer');

        return response()->json(['data' => $offer->fresh(['proposal.job', 'client', 'freelancer'])], 201);
    }

    public function update(Request $request, ProposalOffer $offer, MarketplaceHiringService $hiring, MarketplaceNotificationService $notifications, MarketplaceOfferService $offers, MarketplacePaymentService $payments)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'declined', 'withdrawn'])]]);
        $offers->expireIfDue($offer, $notifications);
        $offer->refresh();
        abort_unless($offer->status === 'pending', 422, 'This offer is no longer awaiting a response.');

        if ($data['status'] === 'withdrawn') {
            abort_unless($offer->client_id === $request->user()->id, 403, 'Only the client can withdraw this offer.');
            $offer->update(['status' => 'withdrawn', 'responded_by' => $request->user()->id, 'responded_at' => now()]);
            $offer->proposal()->where('status', 'offered')->update(['status' => 'shortlisted']);
            $notifications->send($offer->freelancer_id, 'proposal_offer_withdrawn', 'Offer withdrawn', "The offer for {$offer->proposal->job->title} was withdrawn.", '/work?role=freelancer');

            return ['data' => $offer->fresh(['proposal.job'])];
        }

        abort_unless($offer->freelancer_id === $request->user()->id, 403, 'Only the invited freelancer can respond to this offer.');
        if ($data['status'] === 'declined') {
            $offer->update(['status' => 'declined', 'responded_by' => $request->user()->id, 'responded_at' => now()]);
            $offer->proposal()->where('status', 'offered')->update(['status' => 'shortlisted']);
            $notifications->send($offer->client_id, 'proposal_offer_declined', 'Offer declined', "{$request->user()->name} declined the offer for {$offer->proposal->job->title}.", "/search/jobs/{$offer->proposal->job_id}");

            return ['data' => $offer->fresh(['proposal.job'])];
        }

        $contract = $hiring->startContract($offer->proposal, $notifications, $payments, $offer);
        $notifications->send($offer->client_id, 'proposal_offer_accepted', 'Offer accepted', "{$request->user()->name} accepted your offer for {$contract->title}.", "/projects/{$contract->id}");

        return ['data' => $offer->fresh(['proposal.job']), 'contract' => $contract];
    }
}
