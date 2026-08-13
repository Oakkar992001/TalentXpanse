<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class MarketplaceOnboardingService
{
    public const FREELANCER_REWARD_CREDITS = 3;

    public function summaryFor(User $user): array
    {
        $user->loadMissing(['roles', 'freelancerProfile', 'clientProfile', 'portfolioItems', 'freelancerResume']);
        $roles = $user->roles->pluck('name');
        $items = [];

        if ($roles->contains('freelancer')) {
            $readiness = app(ProfileReadinessService::class);
            $checklist = $readiness->freelancerChecklist($user);
            $completion = $readiness->completion($checklist);
            $items = [
                ['key' => 'freelancer_profile', 'label' => 'Make your freelancer profile job-ready', 'completed' => $completion >= 80, 'href' => '/profile', 'detail' => "{$completion}% complete"],
                ['key' => 'freelancer_portfolio', 'label' => 'Add a portfolio item or CV', 'completed' => $user->portfolioItems->isNotEmpty() || filled($user->freelancerResume?->storage_path), 'href' => '/profile', 'detail' => 'Show clients evidence of your work'],
                ['key' => 'freelancer_first_proposal', 'label' => 'Find a suitable job', 'completed' => $user->proposals()->exists(), 'href' => '/search?scope=jobs', 'detail' => 'Apply only when the work is a real match'],
            ];
            $eligible = $completion >= 80 && ! $user->onboarding_rewarded_at;
            $reward = [
                'type' => 'proposal_credits',
                'amount' => self::FREELANCER_REWARD_CREDITS,
                'eligible' => $eligible,
                'awarded_at' => $user->onboarding_rewarded_at?->toIso8601String(),
                'label' => $user->onboarding_rewarded_at ? 'Job-ready profile reward claimed' : 'Complete an 80% job-ready profile to earn 3 Proposal Credits',
            ];
        } elseif ($roles->contains('client')) {
            $items = [
                ['key' => 'client_profile', 'label' => 'Complete your hiring profile', 'completed' => filled($user->clientProfile?->company_name) || filled($user->clientProfile?->company_description), 'href' => '/profile', 'detail' => 'A company name is optional, but context helps freelancers trust the brief'],
                ['key' => 'client_first_job', 'label' => 'Post a clear first job', 'completed' => $user->clientJobs()->exists(), 'href' => '/dashboard?role=client&postJob=1', 'detail' => 'Include a scope, budget and timeline'],
                ['key' => 'client_verification', 'label' => 'Request identity verification', 'completed' => $user->identity_verification_status === 'verified', 'href' => '/settings/verification', 'detail' => 'Optional in beta, recommended before hiring'],
            ];
            $reward = null;
        } else {
            $reward = null;
        }

        $completed = collect($items)->where('completed', true)->count();

        return ['items' => $items, 'completed' => $completed, 'total' => count($items), 'progress' => count($items) ? (int) round($completed / count($items) * 100) : 0, 'reward' => $reward];
    }

    public function claimFreelancerReward(User $user, ProposalCreditService $credits): array
    {
        if (! $user->hasRole('freelancer')) {
            throw ValidationException::withMessages(['reward' => 'Only freelancers can claim this profile reward.']);
        }
        $summary = $this->summaryFor($user->fresh(['roles', 'freelancerProfile', 'portfolioItems', 'freelancerResume']));
        if (! ($summary['reward']['eligible'] ?? false)) {
            throw ValidationException::withMessages(['reward' => 'Complete an 80% job-ready profile before claiming this reward.']);
        }

        return $credits->grantOnboardingCredits($user, self::FREELANCER_REWARD_CREDITS);
    }
}
