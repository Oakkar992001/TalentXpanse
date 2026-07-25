<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\ProposalCreditService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, ProposalCreditService $credits)
    {
        $user = $request->user();
        $role = $request->query('role', $user->roles()->value('name'));
        abort_unless(in_array($role, $user->roles()->pluck('name')->all(), true), 403, 'That role is not enabled on this account.');

        if ($role === 'client') {
            $jobs = $user->clientJobs();

            return ['data' => [
                'role' => 'client',
                'metrics' => [
                    'active_jobs' => (clone $jobs)->where('status', 'open')->count(),
                    'total_proposals' => $user->clientJobs()->withCount('proposals')->get()->sum('proposals_count'),
                    'hired' => $user->clientJobs()->whereHas('proposals', fn ($q) => $q->where('status', 'hired'))->count(),
                ],
                'jobs' => $jobs->withCount('proposals')->latest()->take(6)->get(),
                'recent_proposals' => $user->clientJobs()->with(['proposals' => fn ($q) => $q->with('freelancer.freelancerProfile')->latest()->take(5)])->get()->pluck('proposals')->flatten()->sortByDesc('created_at')->take(5)->values(),
            ]];
        }

        $proposals = $user->proposals();
        $recommendations = Job::with('client.clientProfile')->where('status', 'open')->whereDoesntHave('proposals', fn ($q) => $q->where('freelancer_id', $user->id))->latest()->take(6)->get();

        return ['data' => [
            'role' => 'freelancer',
            'metrics' => [
                'active_proposals' => (clone $proposals)->whereIn('status', ['submitted', 'shortlisted'])->count(),
                'hired' => (clone $proposals)->where('status', 'hired')->count(),
                'profile_completeness' => $user->freelancerProfile?->profile_completeness ?? 0,
            ],
            'proposal_credits' => $credits->summaryFor($user),
            'proposals' => $proposals->with('job')->latest()->take(6)->get(),
            'recommended_jobs' => $recommendations,
        ]];
    }
}
