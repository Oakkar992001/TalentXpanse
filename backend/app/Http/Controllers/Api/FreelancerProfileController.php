<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FreelancerProfile;
use App\Services\ProfileReadinessService;
use App\Services\TrustSummaryService;
use Illuminate\Http\Request;

class FreelancerProfileController extends Controller
{
    public function show(Request $request, TrustSummaryService $trust, ProfileReadinessService $readiness)
    {
        $this->ensureFreelancer($request);

        return ['data' => $this->payload($request, $trust, $readiness)];
    }

    public function update(Request $request, TrustSummaryService $trust, ProfileReadinessService $readiness)
    {
        $this->ensureFreelancer($request);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2500'],
            'hourly_rate' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'availability' => ['sometimes', 'boolean'],
            'skills' => ['nullable', 'array', 'max:15'],
            'skills.*' => ['string', 'max:50'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        $profile = FreelancerProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $profile->update($data);

        return ['data' => $this->payload($request, $trust, $readiness)];
    }

    private function payload(Request $request, TrustSummaryService $trust, ProfileReadinessService $readiness): array
    {
        $user = $request->user()->fresh()->load('freelancerProfile', 'portfolioItems', 'freelancerResume');
        $checklist = $readiness->freelancerChecklist($user);

        return [...$user->toArray(), 'trust_summary' => $trust->for($user), 'profile_checklist' => $checklist, 'profile_completeness' => $readiness->completion($checklist)];
    }

    private function ensureFreelancer(Request $request): void
    {
        abort_unless($request->user()->hasRole('freelancer'), 403, 'Add the Freelancer role to manage a freelancer profile.');
    }
}
