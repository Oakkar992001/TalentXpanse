<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\FreelancerProfile;
use App\Models\Job;
use App\Models\User;
use App\Services\TrustSummaryService;
use App\Services\ProposalCreditService;
use App\Services\MarketplaceReliabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function show(Request $request, ProposalCreditService $credits, MarketplaceReliabilityService $reliability, TrustSummaryService $trust)
    {
        $user = $request->user();
        $role = $request->query('role', $user->roles()->value('name'));
        abort_unless(in_array($role, $user->roles()->pluck('name')->all(), true), 403, 'That role is not enabled on this account.');

        if ($role === 'client') {
            return ['data' => $this->clientDashboard($user, $reliability, $trust)];
        }

        return ['data' => $this->freelancerDashboard($user, $credits, $reliability)];
    }

    private function clientDashboard(User $user, MarketplaceReliabilityService $reliability, TrustSummaryService $trust): array
    {
        $openJobs = $user->clientJobs()->where('status', 'open');
        $activeProjects = $this->activeProjects($user, 'client');
        $activeProjectCount = $this->activeProjectCount($user, 'client');
        $reviewMilestones = ContractMilestone::query()
            ->where('status', 'submitted')
            ->whereHas('contract', fn ($query) => $query->where('client_id', $user->id)->where('status', 'active'))
            ->with('contract.job:id,title')
            ->latest('submitted_at')
            ->take(3)
            ->get();
        $jobsWithApplicants = $user->clientJobs()
            ->where('status', 'open')
            ->withCount('proposals')
            ->latest()
            ->get()
            ->filter(fn (Job $job) => $job->proposals_count > 0)
            ->take(3);

        $actions = $reviewMilestones->map(fn (ContractMilestone $milestone) => $this->actionItem(
            'review_milestone',
            $milestone->title,
            $milestone->contract?->title ?? $milestone->contract?->job?->title,
            "/projects/{$milestone->contract_id}?milestone={$milestone->id}&focus=milestone"
        ))->concat($jobsWithApplicants->map(fn (Job $job) => $this->actionItem(
            'review_proposals',
            $job->title,
            "{$job->proposals_count} application".($job->proposals_count === 1 ? '' : 's')." waiting",
            "/manage/jobs/{$job->id}/proposals"
        )))->take(4)->values();

        return [
            'role' => 'client',
            'metrics' => [
                'active_jobs' => (clone $openJobs)->count(),
                'total_proposals' => $user->clientJobs()->withCount('proposals')->get()->sum('proposals_count'),
                'hired' => $user->clientJobs()->whereHas('proposals', fn ($query) => $query->where('status', 'hired'))->count(),
                'active_projects' => $activeProjectCount,
                'unread_notifications' => $user->marketplaceNotifications()->whereNull('read_at')->count(),
                'saved_talent' => $user->savedTalent()->count(),
            ],
            'jobs' => $openJobs->withCount('proposals')->latest()->take(6)->get(),
            'recent_proposals' => $user->clientJobs()->with(['proposals' => fn ($query) => $query->with('freelancer.freelancerProfile')->latest()->take(5)])->get()->pluck('proposals')->flatten()->sortByDesc('created_at')->take(5)->values(),
            'active_projects' => $activeProjects,
            'action_items' => $actions,
            'notifications' => $this->recentNotifications($user),
            'saved_searches' => $this->savedSearches($user),
            'recommended_talent' => $this->recommendedTalent($user, $trust),
            'reliability' => $reliability->summaryFor($user, 'client'),
        ];
    }

    private function freelancerDashboard(User $user, ProposalCreditService $credits, MarketplaceReliabilityService $reliability): array
    {
        $proposals = $user->proposals();
        $activeProjects = $this->activeProjects($user, 'freelancer');
        $activeProjectCount = $this->activeProjectCount($user, 'freelancer');
        $milestones = ContractMilestone::query()
            ->whereIn('status', ['revision_requested', 'planned', 'in_progress'])
            ->whereHas('contract', fn ($query) => $query->where('freelancer_id', $user->id)->where('status', 'active'))
            ->with('contract.job:id,title')
            ->orderByRaw("case status when 'revision_requested' then 0 when 'planned' then 1 else 2 end")
            ->orderBy('due_date')
            ->take(3)
            ->get();
        $proposalFollowUps = (clone $proposals)->whereIn('status', ['shortlisted', 'interviewing'])->with('job:id,title')->latest()->take(2)->get();

        $actions = $milestones->map(fn (ContractMilestone $milestone) => $this->actionItem(
            match ($milestone->status) {
                'revision_requested' => 'revise_milestone',
                'planned' => 'start_milestone',
                default => 'continue_milestone',
            },
            $milestone->title,
            $milestone->contract?->title ?? $milestone->contract?->job?->title,
            "/projects/{$milestone->contract_id}?milestone={$milestone->id}&focus=milestone"
        ))->concat($proposalFollowUps->map(fn ($proposal) => $this->actionItem(
            'follow_up',
            $proposal->job?->title ?? 'Proposal update',
            ucfirst($proposal->status).' application',
            '/work?role=freelancer'
        )))->take(4)->values();

        return [
            'role' => 'freelancer',
            'metrics' => [
                'active_proposals' => (clone $proposals)->whereIn('status', ['submitted', 'shortlisted'])->count(),
                'hired' => (clone $proposals)->where('status', 'hired')->count(),
                'profile_completeness' => $user->freelancerProfile?->profile_completeness ?? 0,
                'active_projects' => $activeProjectCount,
                'unread_notifications' => $user->marketplaceNotifications()->whereNull('read_at')->count(),
                'saved_jobs' => $user->savedJobs()->count(),
            ],
            'proposal_credits' => $credits->summaryFor($user),
            'proposals' => $proposals->with('job')->latest()->take(6)->get(),
            'recommended_jobs' => $this->recommendedJobs($user),
            'active_projects' => $activeProjects,
            'action_items' => $actions,
            'notifications' => $this->recentNotifications($user),
            'saved_searches' => $this->savedSearches($user),
            'reliability' => $reliability->summaryFor($user, 'freelancer'),
        ];
    }

    private function activeProjects(User $user, string $role): Collection
    {
        return Contract::query()
            ->where("{$role}_id", $user->id)
            ->where('status', 'active')
            ->with(['job:id,title', 'milestones:id,contract_id,title,status,due_date'])
            ->latest('updated_at')
            ->take(4)
            ->get();
    }

    private function activeProjectCount(User $user, string $role): int
    {
        return Contract::query()->where("{$role}_id", $user->id)->where('status', 'active')->count();
    }

    private function recentNotifications(User $user): Collection
    {
        return $user->marketplaceNotifications()
            ->latest()
            ->take(4)
            ->get(['id', 'type', 'title', 'body', 'url', 'read_at', 'created_at']);
    }

    private function savedSearches(User $user): Collection
    {
        return $user->savedSearches()
            ->where('alerts_enabled', true)
            ->latest()
            ->take(3)
            ->get(['id', 'name', 'scope', 'filters', 'alerts_enabled']);
    }

    private function recommendedJobs(User $user): Collection
    {
        $profileSkills = $this->normaliseTerms($user->freelancerProfile?->skills ?? []);
        $pastCategories = $user->proposals()->with('job:id,category')->get()->pluck('job.category')->filter()->unique();
        $savedSearches = $this->savedSearches($user);

        return Job::query()
            ->with('client.clientProfile')
            ->where('status', 'open')
            ->whereDoesntHave('proposals', fn ($query) => $query->where('freelancer_id', $user->id))
            ->latest()
            ->take(36)
            ->get()
            ->map(function (Job $job) use ($profileSkills, $pastCategories, $savedSearches) {
                $matchedSkills = $this->normaliseTerms($job->skills ?? [])->intersect($profileSkills)->values();
                $matchesSavedSearch = $savedSearches->contains(function ($search) use ($job, $matchedSkills) {
                    $filters = $search->filters ?? [];
                    return ($filters['category'] ?? null) === $job->category
                        || ($filters['skill'] ?? null && $matchedSkills->contains($this->normaliseTerm($filters['skill'])));
                });
                $pastCategory = $pastCategories->contains($job->category);
                $score = ($matchedSkills->count() * 12) + ($matchesSavedSearch ? 7 : 0) + ($pastCategory ? 4 : 0) + ($job->created_at?->gte(now()->subDays(7)) ? 1 : 0);
                $job->setAttribute('match', [
                    'skills' => $matchedSkills->take(3)->values(),
                    'saved_search' => $matchesSavedSearch,
                    'past_category' => $pastCategory,
                ]);
                $job->setAttribute('match_score', $score);
                return $job;
            })
            ->sortByDesc('match_score')
            ->take(6)
            ->values();
    }

    private function recommendedTalent(User $user, TrustSummaryService $trust): Collection
    {
        $jobs = $user->clientJobs()->where('status', 'open')->get(['id', 'category', 'skills']);
        $jobSkills = $this->normaliseTerms($jobs->pluck('skills')->flatten()->all());
        $jobCategories = $jobs->pluck('category')->filter()->unique();

        return FreelancerProfile::query()
            ->with('user')
            ->where('availability', true)
            ->latest()
            ->take(36)
            ->get()
            ->map(function (FreelancerProfile $profile) use ($jobSkills, $jobCategories, $trust) {
                $matchedSkills = $this->normaliseTerms($profile->skills ?? [])->intersect($jobSkills)->values();
                $profile->user?->setAttribute('trust_summary', $trust->for($profile->user));
                $profile->setAttribute('match', [
                    'skills' => $matchedSkills->take(3)->values(),
                    'open_job_skills' => $matchedSkills->isNotEmpty(),
                    'available' => (bool) $profile->availability,
                ]);
                $profile->setAttribute('match_score', ($matchedSkills->count() * 12) + ($profile->profile_completeness >= 80 ? 3 : 0) + ($jobCategories->isNotEmpty() ? 1 : 0));
                return $profile;
            })
            ->sortByDesc('match_score')
            ->take(4)
            ->values();
    }

    private function actionItem(string $type, string $label, ?string $context, string $href): array
    {
        return compact('type', 'label', 'context', 'href');
    }

    private function normaliseTerms(array $terms): Collection
    {
        return collect($terms)->map(fn ($term) => $this->normaliseTerm($term))->filter()->unique()->values();
    }

    private function normaliseTerm(?string $term): string
    {
        return mb_strtolower(trim((string) $term));
    }
}
