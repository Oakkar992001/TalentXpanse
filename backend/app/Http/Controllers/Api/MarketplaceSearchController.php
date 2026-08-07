<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FreelancerProfile;
use App\Models\Job;
use App\Services\TrustSummaryService;
use App\Services\MarketplaceReliabilityService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class MarketplaceSearchController extends Controller
{
    public function search(Request $request, TrustSummaryService $trust, MarketplaceReliabilityService $reliability)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(['all', 'jobs', 'talent'])],
            'category' => ['nullable', 'string', 'max:100'],
            'skill' => ['nullable', 'string', 'max:100'],
            'budget_type' => ['nullable', Rule::in(['fixed', 'hourly'])],
            'experience_level' => ['nullable', Rule::in(['entry', 'intermediate', 'expert'])],
            'min_budget' => ['nullable', 'numeric', 'min:0'],
            'max_budget' => ['nullable', 'numeric', 'min:0', 'gte:min_budget'],
            'location' => ['nullable', 'string', 'max:100'],
            'min_rate' => ['nullable', 'numeric', 'min:0'],
            'max_rate' => ['nullable', 'numeric', 'min:0', 'gte:min_rate'],
            'availability' => ['nullable', Rule::in(['all', 'available'])],
            'sort' => ['nullable', Rule::in(['newest', 'budget_high', 'budget_low', 'rate_high', 'rate_low'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:6', 'max:24'],
        ]);

        $term = trim($data['q'] ?? '');
        $scope = $data['scope'] ?? 'all';
        $perPage = $data['per_page'] ?? 9;
        $pagination = ['jobs' => null, 'talent' => null];
        $jobs = collect();
        $talent = collect();

        if ($scope !== 'talent') {
            $jobsQuery = Job::query()
                ->where('status', 'open')
                ->when($term !== '', fn ($query) => $query->where(fn ($jobs) => $jobs->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")->orWhere('skills', 'like', "%{$term}%")))
                ->when($data['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
                ->when($data['skill'] ?? null, fn ($query, $skill) => $query->whereJsonContains('skills', $skill))
                ->when($data['budget_type'] ?? null, fn ($query, $budgetType) => $query->where('budget_type', $budgetType))
                ->when($data['experience_level'] ?? null, fn ($query, $experience) => $query->where('experience_level', $experience))
                ->when($data['min_budget'] ?? null, fn ($query, $minimum) => $query->where('budget_max', '>=', $minimum))
                ->when($data['max_budget'] ?? null, fn ($query, $maximum) => $query->where('budget_min', '<=', $maximum))
                ->with('client.clientProfile');

            match ($data['sort'] ?? 'newest') {
                'budget_high' => $jobsQuery->orderByDesc('budget_max'),
                'budget_low' => $jobsQuery->orderBy('budget_min'),
                default => $jobsQuery->latest(),
            };

            if ($scope === 'jobs') {
                $page = $jobsQuery->paginate($perPage)->withQueryString();
                $jobs = $page->getCollection();
                $pagination['jobs'] = $this->paginationMeta($page);
            } else {
                $jobs = $jobsQuery->take(6)->get();
            }
        }

        if ($scope !== 'jobs') {
            $talentQuery = FreelancerProfile::query()
                ->leftJoin('marketplace_reliability_profiles as reliability_profiles', function ($join) {
                    $join->on('freelancer_profiles.user_id', '=', 'reliability_profiles.user_id')
                        ->where('reliability_profiles.role', '=', 'freelancer');
                })
                ->select('freelancer_profiles.*')
                ->when($term !== '', fn ($query) => $query->where(fn ($profiles) => $profiles->where('title', 'like', "%{$term}%")->orWhere('location', 'like', "%{$term}%")->orWhere('skills', 'like', "%{$term}%")->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$term}%"))))
                ->when($data['skill'] ?? null, fn ($query, $skill) => $query->whereJsonContains('skills', $skill))
                ->when($data['location'] ?? null, fn ($query, $location) => $query->where('location', 'like', "%{$location}%"))
                ->when($data['min_rate'] ?? null, fn ($query, $minimum) => $query->where('hourly_rate', '>=', $minimum))
                ->when($data['max_rate'] ?? null, fn ($query, $maximum) => $query->where('hourly_rate', '<=', $maximum))
                ->when(($data['availability'] ?? null) === 'available', fn ($query) => $query->where('availability', true))
                ->with('user');

            $talentQuery->orderByRaw("case reliability_profiles.search_visibility when 'limited' then 2 when 'reduced' then 1 else 0 end");

            match ($data['sort'] ?? 'newest') {
                'rate_high' => $talentQuery->orderByDesc('hourly_rate'),
                'rate_low' => $talentQuery->orderBy('hourly_rate'),
                default => $talentQuery->latest(),
            };

            if ($scope === 'talent') {
                $page = $talentQuery->paginate($perPage)->withQueryString();
                $talent = $page->getCollection();
                $pagination['talent'] = $this->paginationMeta($page);
            } else {
                $talent = $talentQuery->take(6)->get();
            }

            $talent = $talent->map(function (FreelancerProfile $profile) use ($trust, $reliability) {
                $profile->user?->setAttribute('trust_summary', $trust->for($profile->user));
                $summary = $reliability->summaryFor($profile->user, 'freelancer', false);
                $profile->user?->setAttribute('reliability_summary', collect($summary)->only([
                    'tier',
                    'tier_label',
                    'completed_projects_count',
                    'positive_reviews_count',
                    'average_rating',
                ])->all());
                return $profile;
            })->values();
        }

        return ['data' => ['jobs' => $jobs, 'talent' => $talent, 'pagination' => $pagination, 'can_search_talent' => true]];
    }

    private function paginationMeta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }
}
