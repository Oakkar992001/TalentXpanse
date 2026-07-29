<?php

namespace App\Services;

use App\Models\Job;
use App\Models\MarketplaceSavedSearch;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceSavedSearchAlertService
{
    public function sendDueAlerts(MarketplaceNotificationService $notifications): int
    {
        $sent = 0;
        MarketplaceSavedSearch::query()
            ->where('scope', 'jobs')
            ->where('alerts_enabled', true)
            ->where('alert_frequency', 'daily')
            ->where(fn (Builder $query) => $query->whereNull('last_alerted_at')->orWhere('last_alerted_at', '<=', now()->subDay()))
            ->with('user')
            ->orderBy('id')
            ->each(function (MarketplaceSavedSearch $search) use ($notifications, &$sent) {
                $since = $search->last_alerted_at ?? now()->subDay();
                $matches = $this->applyFilters(Job::query()->where('status', 'open')->where('created_at', '>', $since), $search->filters ?: [])
                    ->latest()
                    ->take(3)
                    ->get(['id', 'title']);
                $search->update(['last_alerted_at' => now()]);
                if ($matches->isEmpty()) return;

                $names = $matches->pluck('title')->join(', ');
                $count = $matches->count();
                $notifications->send($search->user, 'job_alert', "New jobs for {$search->name}", "{$count} new matching job".($count === 1 ? '' : 's').": {$names}", '/search?'.http_build_query(['scope' => 'jobs', ...$search->filters]));
                $sent++;
            });

        return $sent;
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        $term = trim((string) ($filters['q'] ?? ''));
        return $query
            ->when($term !== '', fn (Builder $jobs) => $jobs->where(fn (Builder $items) => $items->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")->orWhere('skills', 'like', "%{$term}%")))
            ->when($filters['category'] ?? null, fn (Builder $jobs, $value) => $jobs->where('category', $value))
            ->when($filters['skill'] ?? null, fn (Builder $jobs, $value) => $jobs->whereJsonContains('skills', $value))
            ->when($filters['budget_type'] ?? null, fn (Builder $jobs, $value) => $jobs->where('budget_type', $value))
            ->when($filters['experience_level'] ?? null, fn (Builder $jobs, $value) => $jobs->where('experience_level', $value))
            ->when($filters['min_budget'] ?? null, fn (Builder $jobs, $value) => $jobs->where('budget_max', '>=', $value))
            ->when($filters['max_budget'] ?? null, fn (Builder $jobs, $value) => $jobs->where('budget_min', '<=', $value));
    }
}
