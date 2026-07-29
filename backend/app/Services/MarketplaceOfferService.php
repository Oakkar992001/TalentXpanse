<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\ProposalOffer;
use Illuminate\Support\Facades\DB;

class MarketplaceOfferService
{
    public function expireDueOffers(MarketplaceNotificationService $notifications): int
    {
        $expired = 0;

        ProposalOffer::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($offers) use (&$expired, $notifications) {
                foreach ($offers as $offer) {
                    $expired += $this->expireIfDue($offer, $notifications) ? 1 : 0;
                }
            });

        return $expired;
    }

    public function expireIfDue(ProposalOffer $offer, MarketplaceNotificationService $notifications): bool
    {
        if ($offer->status !== 'pending' || ! $offer->expires_at || $offer->expires_at->isFuture()) {
            return false;
        }

        $expired = DB::transaction(function () use ($offer) {
            $lockedOffer = ProposalOffer::query()->lockForUpdate()->with('proposal.job')->findOrFail($offer->id);
            if ($lockedOffer->status !== 'pending' || ! $lockedOffer->expires_at || $lockedOffer->expires_at->isFuture()) {
                return null;
            }

            $lockedOffer->update(['status' => 'expired']);
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($lockedOffer->proposal_id);
            if ($proposal->status === 'offered' && ! $proposal->offers()->where('status', 'pending')->exists()) {
                $proposal->update(['status' => 'shortlisted']);
            }

            return $lockedOffer;
        });

        if (! $expired) {
            return false;
        }

        $jobTitle = $expired->proposal?->job?->title ?: 'this project';
        $notifications->send($expired->freelancer_id, 'proposal_offer_expired', 'Offer expired', "The offer for {$jobTitle} expired before it was accepted.", '/work?role=freelancer');
        $notifications->send($expired->client_id, 'proposal_offer_expired', 'Offer expired', "Your offer for {$jobTitle} expired. You can send revised terms from Proposal manager.", "/manage/jobs/{$expired->proposal?->job_id}/proposals");

        return true;
    }
}
