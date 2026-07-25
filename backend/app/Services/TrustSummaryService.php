<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractReview;
use App\Models\User;

class TrustSummaryService
{
    public function for(User $user): array
    {
        $contracts = Contract::query()
            ->where('status', 'completed')
            ->where(fn ($query) => $query->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->with(['client.clientProfile', 'freelancer'])
            ->latest('completed_at')
            ->get();

        $visibleReviews = ContractReview::query()
            ->where('reviewed_user_id', $user->id)
            ->with('contract.reviews')
            ->get()
            ->filter(fn (ContractReview $review) => $review->contract?->status === 'completed' && ($review->contract->reviews->count() === 2 || $review->contract->completed_at?->lte(now()->subDays(14))))
            ->values();

        return [
            'average_rating' => $visibleReviews->isNotEmpty() ? round((float) $visibleReviews->avg('rating'), 1) : null,
            'review_count' => $visibleReviews->count(),
            'completed_projects_count' => $contracts->count(),
            'completed_projects' => $contracts->take(5)->map(function (Contract $contract) use ($user) {
                $isClient = $contract->client_id === $user->id;

                return [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'completed_at' => $contract->completed_at,
                    'amount' => $contract->agreed_amount,
                    'partner_name' => $isClient ? $contract->freelancer?->name : ($contract->client?->clientProfile?->company_name ?: $contract->client?->name),
                ];
            })->values(),
        ];
    }
}
