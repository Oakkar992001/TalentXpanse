<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return ['data' => Contract::query()
            ->where(fn ($query) => $query->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->with(['job', 'client', 'freelancer', 'milestones'])
            ->latest()
            ->get()];
    }

    public function show(Request $request, Contract $contract)
    {
        $this->authorizeParticipant($request, $contract);

        return ['data' => $this->payload($contract, $request->user()->id)];
    }

    public function storeMilestone(Request $request, Contract $contract, MarketplaceNotificationService $notifications)
    {
        abort_unless($contract->client_id === $request->user()->id, 403, 'Only the client can create milestones.');
        abort_unless($contract->status === 'active', 422, 'This contract is no longer active.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'integer', 'min:1000'],
            'due_date' => ['nullable', 'date'],
        ]);
        $allocated = $contract->milestones()->sum('amount');
        abort_if($allocated + $data['amount'] > $contract->agreed_amount, 422, 'Milestone amounts cannot exceed the agreed contract amount.');

        $milestone = $contract->milestones()->create($data);
        $this->event($contract, 'milestone_created', "Milestone created: {$milestone->title}");
        $notifications->send($contract->freelancer_id, 'milestone_created', 'New milestone created', "{$milestone->title} was added to {$contract->title}.", "/projects/{$contract->id}");

        return response()->json(['data' => $milestone], 201);
    }

    public function updateMilestone(Request $request, ContractMilestone $milestone, MarketplaceNotificationService $notifications)
    {
        $contract = $milestone->contract;
        $this->authorizeParticipant($request, $contract);
        abort_unless($contract->status === 'active', 422, 'This contract is no longer active.');
        $action = $request->validate(['action' => ['required', Rule::in(['start', 'submit', 'request_revision', 'approve'])]])['action'];

        if ($action === 'start') {
            abort_unless($contract->freelancer_id === $request->user()->id && $milestone->status === 'planned', 422, 'Only the freelancer can start a planned milestone.');
            $milestone->update(['status' => 'in_progress']);
            $this->event($contract, 'milestone_started', "Milestone started: {$milestone->title}");
            $notifications->send($contract->client_id, 'milestone_started', 'Milestone started', "{$milestone->title} is now in progress.", "/projects/{$contract->id}");
        }
        if ($action === 'submit') {
            abort_unless($contract->freelancer_id === $request->user()->id && in_array($milestone->status, ['planned', 'in_progress', 'revision_requested'], true), 422, 'This milestone cannot be submitted now.');
            $milestone->update(['status' => 'submitted', 'submitted_at' => now()]);
            $this->event($contract, 'milestone_submitted', "Milestone submitted for review: {$milestone->title}");
            $notifications->send($contract->client_id, 'milestone_submitted', 'Milestone ready for review', "{$milestone->title} was submitted for your review.", "/projects/{$contract->id}");
        }
        if ($action === 'request_revision') {
            abort_unless($contract->client_id === $request->user()->id && $milestone->status === 'submitted', 422, 'Only the client can request a revision for a submitted milestone.');
            $milestone->update(['status' => 'revision_requested']);
            $this->event($contract, 'revision_requested', "Revision requested: {$milestone->title}");
            $notifications->send($contract->freelancer_id, 'revision_requested', 'Revision requested', "The client requested changes to {$milestone->title}.", "/projects/{$contract->id}");
        }
        if ($action === 'approve') {
            abort_unless($contract->client_id === $request->user()->id && $milestone->status === 'submitted', 422, 'Only the client can approve a submitted milestone.');
            $milestone->update(['status' => 'approved', 'approved_at' => now()]);
            $this->event($contract, 'milestone_approved', "Milestone approved: {$milestone->title}");
            $notifications->send($contract->freelancer_id, 'milestone_approved', 'Milestone approved', "{$milestone->title} was approved.", "/projects/{$contract->id}");
        }

        return ['data' => $milestone->fresh()];
    }

    public function complete(Request $request, Contract $contract, MarketplaceNotificationService $notifications)
    {
        abort_unless($contract->client_id === $request->user()->id, 403, 'Only the client can complete a contract.');
        abort_unless($contract->status === 'active', 422, 'This contract is no longer active.');
        abort_unless($contract->milestones()->exists() && ! $contract->milestones()->where('status', '!=', 'approved')->exists(), 422, 'Approve every milestone before completing this contract.');

        $contract->update(['status' => 'completed', 'completed_at' => now()]);
        $this->event($contract, 'contract_completed', 'Contract completed. Both people can now leave a private project review.');
        $notifications->send($contract->freelancer_id, 'contract_completed', 'Project completed', "{$contract->title} is complete. You can now leave a private review.", "/projects/{$contract->id}");

        return ['data' => $contract->fresh('milestones')];
    }

    private function payload(Contract $contract, int $viewerId): array
    {
        $contract->load(['job', 'client', 'freelancer', 'milestones', 'reviews.reviewer', 'supportRequests.opener', 'supportRequests.handler']);
        $payload = $contract->toArray();
        $reviews = $contract->reviews;
        $bothReviewed = $reviews->count() === 2;
        $windowClosed = $contract->completed_at?->lte(now()->subDays(14)) ?? false;

        $payload['reviews'] = $reviews->map(function ($review) use ($viewerId, $bothReviewed, $windowClosed) {
            $isOwnReview = $review->reviewer_id === $viewerId;

            return [
                'id' => $review->id,
                'reviewer_id' => $review->reviewer_id,
                'reviewed_user_id' => $review->reviewed_user_id,
                'reviewer' => $review->reviewer,
                'rating' => $isOwnReview || $bothReviewed || $windowClosed ? $review->rating : null,
                'comment' => $isOwnReview || $bothReviewed || $windowClosed ? $review->comment : null,
                'is_visible' => $isOwnReview || $bothReviewed || $windowClosed,
                'created_at' => $review->created_at,
            ];
        })->values();

        return $payload;
    }

    private function event(Contract $contract, string $type, string $body): void
    {
        $conversation = Conversation::where('proposal_id', $contract->proposal_id)->first();
        if (! $conversation) {
            return;
        }
        ConversationEvent::create(['conversation_id' => $conversation->id, 'contract_id' => $contract->id, 'type' => $type, 'body' => $body]);
        $conversation->update(['last_message_at' => now()]);
    }

    private function authorizeParticipant(Request $request, Contract $contract): void
    {
        abort_unless(in_array($request->user()->id, [$contract->client_id, $contract->freelancer_id], true), 403, 'You are not part of this contract.');
    }
}
