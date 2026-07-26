<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FreelancerProfile;
use App\Models\Job;
use App\Models\MarketplaceSavedJob;
use App\Models\MarketplaceSavedTalent;
use App\Services\TrustSummaryService;
use Illuminate\Http\Request;

class MarketplaceSaveController extends Controller
{
    public function index(Request $request, TrustSummaryService $trust)
    {
        $user = $request->user();
        $savedJobs = $user->savedJobs()->with('job.client.clientProfile')->latest()->get();
        $savedTalent = $user->savedTalent()->with('freelancerProfile.user')->latest()->get();

        $savedJobs->each(fn (MarketplaceSavedJob $saved) => $saved->job?->client?->setAttribute('trust_summary', $trust->for($saved->job->client)));
        $savedTalent->each(fn (MarketplaceSavedTalent $saved) => $saved->freelancerProfile?->user?->setAttribute('trust_summary', $trust->for($saved->freelancerProfile->user)));

        return ['data' => [
            'job_ids' => $savedJobs->pluck('job_id')->values(),
            'talent_ids' => $savedTalent->pluck('freelancer_profile_id')->values(),
            'jobs' => $savedJobs->pluck('job')->filter()->values(),
            'talent' => $savedTalent->pluck('freelancerProfile')->filter()->values(),
        ]];
    }

    public function saveJob(Request $request, Job $job)
    {
        $this->ensureFreelancer($request);
        abort_unless($job->status === 'open', 422, 'Only open jobs can be saved.');
        MarketplaceSavedJob::firstOrCreate(['user_id' => $request->user()->id, 'job_id' => $job->id]);

        return response()->json(['data' => ['saved' => true]]);
    }

    public function removeJob(Request $request, Job $job)
    {
        $this->ensureFreelancer($request);
        MarketplaceSavedJob::query()->where('user_id', $request->user()->id)->where('job_id', $job->id)->delete();

        return response()->json(['data' => ['saved' => false]]);
    }

    public function saveTalent(Request $request, FreelancerProfile $freelancerProfile)
    {
        $this->ensureClient($request);
        MarketplaceSavedTalent::firstOrCreate(['user_id' => $request->user()->id, 'freelancer_profile_id' => $freelancerProfile->id]);

        return response()->json(['data' => ['saved' => true]]);
    }

    public function removeTalent(Request $request, FreelancerProfile $freelancerProfile)
    {
        $this->ensureClient($request);
        MarketplaceSavedTalent::query()->where('user_id', $request->user()->id)->where('freelancer_profile_id', $freelancerProfile->id)->delete();

        return response()->json(['data' => ['saved' => false]]);
    }

    private function ensureFreelancer(Request $request): void
    {
        abort_unless($request->user()->hasRole('freelancer'), 403, 'Add the Freelancer role before saving jobs.');
    }

    private function ensureClient(Request $request): void
    {
        abort_unless($request->user()->hasRole('client'), 403, 'Add the Client role before saving freelancers.');
    }
}
