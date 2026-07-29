<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Services\MarketplacePaymentService;
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

    public function show(Request $request, Contract $contract, MarketplacePaymentService $payments)
    {
        $this->authorizeParticipant($request, $contract);

        return ['data' => $this->payload($contract, $request->user()->id, $payments)];
    }

    public function storeMilestone(Request $request, Contract $contract, MarketplaceNotificationService $notifications, MarketplacePaymentService $payments)
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

        $quote = $payments->quote($data['amount']);
        $milestone = $contract->milestones()->create($data + [
            'platform_fee_basis_points' => $quote['platform_fee_basis_points'],
            'client_fee_amount' => $quote['platform_fee_amount'],
            'client_total_amount' => $quote['client_total_amount'],
            'funding_status' => $payments->gatewayConfigured() ? 'awaiting_funding' : 'not_configured',
        ]);
        $this->event($contract, 'milestone_created', "Milestone created: {$milestone->title}");
        $notifications->send($contract->freelancer_id, 'milestone_created', 'New milestone created', "{$milestone->title} was added to {$contract->title}.", "/projects/{$contract->id}");

        return response()->json(['data' => $milestone], 201);
    }

    public function updateMilestone(Request $request, ContractMilestone $milestone, MarketplaceNotificationService $notifications)
    {
        $contract = $milestone->contract;
        $this->authorizeParticipant($request, $contract);
        abort_unless($contract->status === 'active', 422, 'This contract is no longer active.');
        $action = $request->validate(['action' => ['required', Rule::in(['start', 'request_revision', 'approve'])]])['action'];

        if ($action === 'start') {
            abort_unless($contract->freelancer_id === $request->user()->id && $milestone->status === 'planned', 422, 'Only the freelancer can start a planned milestone.');
            $milestone->update(['status' => 'in_progress']);
            $this->event($contract, 'milestone_started', "Milestone started: {$milestone->title}");
            $notifications->send($contract->client_id, 'milestone_started', 'Milestone started', "{$milestone->title} is now in progress.", "/projects/{$contract->id}");
        }
        if ($action === 'request_revision') {
            abort_unless($contract->client_id === $request->user()->id && $milestone->status === 'submitted', 422, 'Only the client can request a revision for a submitted milestone.');
            $revisionNote = $request->validate(['revision_note' => ['required', 'string', 'min:10', 'max:2000']])['revision_note'];
            $submission = $milestone->submissions()->where('status', 'submitted')->first();
            abort_unless($submission, 422, 'A delivery submission is required before requesting a revision.');
            $submission->update(['status' => 'revision_requested', 'review_note' => $revisionNote, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $milestone->update(['status' => 'revision_requested']);
            $this->event($contract, 'revision_requested', "Revision requested: {$milestone->title}");
            $notifications->send($contract->freelancer_id, 'revision_requested', 'Revision requested', "The client requested changes to {$milestone->title}.", "/projects/{$contract->id}");
        }
        if ($action === 'approve') {
            abort_unless($contract->client_id === $request->user()->id && $milestone->status === 'submitted', 422, 'Only the client can approve a submitted milestone.');
            $submission = $milestone->submissions()->where('status', 'submitted')->first();
            abort_unless($submission, 422, 'A delivery submission is required before approval.');
            $submission->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $milestone->update(['status' => 'approved', 'approved_at' => now()]);
            $this->event($contract, 'milestone_approved', "Milestone approved: {$milestone->title}");
            $notifications->send($contract->freelancer_id, 'milestone_approved', 'Milestone approved', "{$milestone->title} was approved.", "/projects/{$contract->id}");
        }

        return ['data' => $milestone->fresh()];
    }

    public function complete(Request $request, Contract $contract, MarketplaceNotificationService $notifications, MarketplacePaymentService $payments)
    {
        abort_unless($contract->client_id === $request->user()->id, 403, 'Only the client can complete a contract.');
        abort_unless($contract->status === 'active', 422, 'This contract is no longer active.');
        abort_if($contract->payment_hold_status === 'on_hold', 422, 'Resolve the active payment safety hold before completing this project.');
        abort_unless($this->hasApprovedEveryMilestone($contract), 422, 'Approve every milestone before completing this contract.');
        abort_unless($payments->canComplete($contract), 422, 'Every approved milestone must be released before completing this contract.');

        $contract->update(['status' => 'completed', 'completed_at' => now()]);
        $contract->job?->update(['status' => 'completed']);
        $event = $contract->freelancer_completion_requested_at
            ? 'The client confirmed completion after the freelancer marked the work ready.'
            : 'The client completed the contract after approving every milestone.';
        $this->event($contract, 'contract_completed', "{$event} Both people can now leave a private project review.");
        $notifications->send($contract->freelancer_id, 'contract_completed', 'Project completed', "{$contract->title} is complete. You can now leave a private review.", "/projects/{$contract->id}");

        return ['data' => $contract->fresh('milestones')];
    }

    public function requestCompletion(Request $request, Contract $contract, MarketplaceNotificationService $notifications)
    {
        abort_unless($contract->freelancer_id === $request->user()->id, 403, 'Only the freelancer can mark the work ready for completion.');
        abort_unless($contract->status === 'active', 422, 'Only an active project can be marked ready for completion.');
        abort_if($contract->payment_hold_status === 'on_hold', 422, 'Resolve the active payment safety hold before requesting completion.');
        abort_unless($this->hasApprovedEveryMilestone($contract), 422, 'Every milestone must be approved before requesting project completion.');
        abort_if($contract->freelancer_completion_requested_at, 422, 'You already marked this project ready for completion.');
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $contract->update([
            'freelancer_completion_requested_at' => now(),
            'freelancer_completion_note' => blank($data['note'] ?? null) ? null : $data['note'],
        ]);
        $this->event($contract, 'completion_requested', 'Freelancer marked all approved work ready for the client to complete the project.');
        $notifications->send($contract->client_id, 'project_completion_requested', 'Project ready for completion', "{$request->user()->name} marked {$contract->title} ready for you to complete.", "/projects/{$contract->id}");

        return ['data' => $contract->fresh('milestones')];
    }

    public function close(Request $request, Contract $contract, MarketplaceNotificationService $notifications)
    {
        $this->authorizeParticipant($request, $contract);
        abort_unless($contract->status === 'active', 422, 'Only an active project can be closed.');
        abort_if($contract->payment_hold_status === 'on_hold', 422, 'Resolve the active payment safety hold before closing this project.');
        abort_if($contract->milestones()->whereIn('status', ['in_progress', 'submitted', 'revision_requested'])->exists(), 422, 'Use a project support request before closing a project with active or submitted delivery work.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:20', 'max:2000']]);

        $contract->update([
            'status' => 'cancelled',
            'closed_by' => $request->user()->id,
            'close_reason' => $data['reason'],
            'closed_at' => now(),
        ]);
        $contract->job?->update(['status' => 'cancelled']);
        $this->event($contract, 'contract_closed', 'Project closed. A project partner recorded a closing reason.');
        $partnerId = $contract->client_id === $request->user()->id ? $contract->freelancer_id : $contract->client_id;
        $notifications->send($partnerId, 'contract_closed', 'Project closed', "{$contract->title} was closed. Review the project activity for the recorded reason.", "/projects/{$contract->id}");

        return ['data' => $contract->fresh(['milestones', 'closer'])];
    }

    private function payload(Contract $contract, int $viewerId, MarketplacePaymentService $payments): array
    {
        $contract->load(['job', 'client', 'freelancer', 'closer', 'milestones.submissions.files', 'milestones.submissions.submitter', 'milestones.submissions.reviewer', 'reviews.reviewer', 'supportRequests.opener', 'supportRequests.handler', 'scopeChangeRequests.requester', 'scopeChangeRequests.responder']);
        $payload = $contract->toArray();
        $payload['payment_policy'] = $payments->policy();
        $payload['payment_safety'] = $payments->safety($contract);
        $payload['milestones'] = $contract->milestones->map(function (ContractMilestone $milestone) use ($payments) {
            return [...$milestone->toArray(), 'payment_summary' => $payments->summary($milestone)];
        })->values();
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
        $payload['activity'] = ConversationEvent::query()
            ->where('contract_id', $contract->id)
            ->latest('created_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

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

    private function hasApprovedEveryMilestone(Contract $contract): bool
    {
        return $contract->milestones()->exists() && ! $contract->milestones()->where('status', '!=', 'approved')->exists();
    }
}
