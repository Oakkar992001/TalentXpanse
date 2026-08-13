<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceOnboardingService;
use App\Services\MarketplaceProductAnalyticsService;
use App\Services\ProposalCreditService;
use Illuminate\Http\Request;

class MarketplaceOnboardingController extends Controller
{
    public function show(Request $request, MarketplaceOnboardingService $onboarding, MarketplaceProductAnalyticsService $analytics)
    {
        $analytics->track($request->user(), 'onboarding_viewed');

        return ['data' => $onboarding->summaryFor($request->user())];
    }

    public function claimReward(Request $request, MarketplaceOnboardingService $onboarding, ProposalCreditService $credits, MarketplaceProductAnalyticsService $analytics)
    {
        $result = $onboarding->claimFreelancerReward($request->user(), $credits);
        $analytics->track($request->user(), 'onboarding_reward_claimed', ['credits' => MarketplaceOnboardingService::FREELANCER_REWARD_CREDITS]);

        return response()->json(['data' => $result, 'message' => '3 Proposal Credits were added to your account.'], 201);
    }
}
