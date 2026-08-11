<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MarketplaceReliabilityService;
use App\Services\TrustSummaryService;

class PublicFreelancerController extends Controller
{
    public function show(User $user, TrustSummaryService $trust, MarketplaceReliabilityService $reliability)
    {
        abort_unless($user->freelancerProfile()->exists(), 404, 'Freelancer profile not found.');
        $user->load('freelancerProfile', 'portfolioItems');
        $profile = $user->freelancerProfile;

        return ['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'profile_photo_url' => $user->profile_photo_url,
            'freelancer_profile' => [
                'id' => $profile->id,
                'title' => $profile->title,
                'experience_level' => $profile->experience_level,
                'bio' => $profile->bio,
                'hourly_rate' => $profile->hourly_rate,
                'availability' => $profile->availability,
                'skills' => $profile->skills,
                'location' => $profile->location,
            ],
            'portfolio_items' => $user->portfolioItems->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'project_url' => $item->project_url,
                'image_url' => $item->image_url,
            ])->values(),
            'trust_summary' => $trust->for($user),
            'reliability' => $reliability->publicSummaryFor($user, 'freelancer'),
        ]];
    }
}
