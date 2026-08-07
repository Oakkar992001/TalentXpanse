<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractReview;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Services\MarketplaceNotificationService;
use App\Services\MarketplaceReliabilityService;
use Illuminate\Http\Request;

class ContractReviewController extends Controller
{
    public function store(Request $request, Contract $contract, MarketplaceNotificationService $notifications, MarketplaceReliabilityService $reliability)
    {
        $this->authorizeParticipant($request, $contract);
        abort_unless($contract->status === 'completed', 422, 'Reviews are available after the project is completed.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);
        $reviewerId = $request->user()->id;
        $reviewedUserId = $contract->client_id === $reviewerId ? $contract->freelancer_id : $contract->client_id;

        abort_if($contract->reviews()->where('reviewer_id', $reviewerId)->exists(), 422, 'You have already submitted your review for this project.');

        $review = ContractReview::create([
            ...$data,
            'contract_id' => $contract->id,
            'reviewer_id' => $reviewerId,
            'reviewed_user_id' => $reviewedUserId,
        ]);
        $reliability->recordPositiveReview($review->load('reviewedUser'), $contract);

        $this->event($contract, 'review_submitted', "{$request->user()->name} submitted a project review.");
        $notifications->send($reviewedUserId, 'review_submitted', 'A project review was submitted', 'Your review remains private until you submit yours or the 14-day window ends.', "/projects/{$contract->id}");

        return response()->json(['data' => $review], 201);
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
