<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Proposal;
use App\Models\ProposalCreditAccount;
use App\Models\ProposalCreditGrant;
use App\Models\ProposalCreditTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposalCreditService
{
    public const FREE_MONTHLY_ALLOWANCE = 20;

    public const FREE_BALANCE_CAP = 40;

    public const FREE_EXPIRY_DAYS = 60;

    public const PREMIUM_MONTHLY_ALLOWANCE = 60;

    public const PREMIUM_BALANCE_CAP = 180;

    public const PREMIUM_EXPIRY_DAYS = 180;

    public const PURCHASED_EXPIRY_DAYS = 365;

    public function costFor(Job $job): int
    {
        $budget = (int) ($job->budget_max ?? $job->budget_min ?? 0);

        return match (true) {
            $budget > 500000 => 4,
            $budget >= 100000 => 2,
            default => 1,
        };
    }

    public function accountFor(User $user): ProposalCreditAccount
    {
        return DB::transaction(function () use ($user) {
            $account = $this->lockedAccount($user);
            $this->prepareAccount($account);

            return $account->fresh();
        });
    }

    public function deductForProposal(User $user, Job $job, ?Proposal $proposal = null): int
    {
        return DB::transaction(function () use ($user, $job, $proposal) {
            $account = $this->lockedAccount($user);
            $this->prepareAccount($account);
            $cost = $this->costFor($job);

            if ($account->balance < $cost) {
                throw ValidationException::withMessages([
                    'credits' => "This proposal costs {$cost} credits, but you have {$account->balance}. Your next monthly grant is available next month.",
                ]);
            }

            $remainingToSpend = $cost;
            $this->spendableGrants($account)->each(function (ProposalCreditGrant $grant) use (&$remainingToSpend) {
                if ($remainingToSpend === 0) {
                    return false;
                }

                $amount = min($remainingToSpend, $grant->remaining_amount);
                $grant->decrement('remaining_amount', $amount);
                $remainingToSpend -= $amount;
            });

            $balance = $this->syncBalance($account);
            ProposalCreditTransaction::create([
                'user_id' => $user->id,
                'proposal_id' => $proposal?->id,
                'type' => 'proposal_submission',
                'amount' => -$cost,
                'balance_after' => $balance,
                'description' => "Proposal for: {$job->title}",
            ]);

            return $cost;
        });
    }

    /**
     * Re-credit an application only when the job was cancelled before anyone was hired.
     * This is intentionally not called for withdrawals or declined proposals.
     */
    public function refundForCancelledJob(Job $job): void
    {
        if ($job->proposals()->where('status', 'hired')->exists()) {
            return;
        }

        $job->proposals()
            ->where('credit_cost', '>', 0)
            ->with('freelancer')
            ->get()
            ->each(fn (Proposal $proposal) => $this->refundProposal($proposal));
    }

    /**
     * Reserved for the future verified checkout flow. No public endpoint calls this method.
     */
    public function grantPurchasedCredits(User $user, int $amount, string $reference): ProposalCreditGrant
    {
        if ($amount < 1) {
            throw ValidationException::withMessages(['credits' => 'Purchased credit amount must be at least one.']);
        }

        return $this->grantCredits($user, $amount, 'purchased', now()->addDays(self::PURCHASED_EXPIRY_DAYS), $reference);
    }

    public function summaryFor(User $user): array
    {
        $account = $this->accountFor($user);
        $plan = $this->planFor($account);
        $grants = $this->activeGrants($account)->map(fn (ProposalCreditGrant $grant) => [
            'id' => $grant->id,
            'source' => $grant->source,
            'label' => $this->sourceLabel($grant->source),
            'remaining_amount' => $grant->remaining_amount,
            'initial_amount' => $grant->initial_amount,
            'granted_at' => $grant->granted_at?->toIso8601String(),
            'expires_at' => $grant->expires_at?->toIso8601String(),
        ])->values();
        $earliest = $grants->filter(fn (array $grant) => $grant['expires_at'])->sortBy('expires_at')->first();

        return [
            'balance' => $account->balance,
            'membership_tier' => $plan['tier'],
            'membership_label' => $plan['label'],
            'monthly_allowance' => $plan['monthly_allowance'],
            'balance_cap' => $plan['balance_cap'],
            'credit_expiry_days' => $plan['expiry_days'],
            'purchased_credit_expiry_days' => self::PURCHASED_EXPIRY_DAYS,
            'next_grant_on' => now()->startOfMonth()->addMonth()->toDateString(),
            'earliest_expiry' => $earliest ? [
                'credits' => $earliest['remaining_amount'],
                'expires_at' => $earliest['expires_at'],
            ] : null,
            'grants' => $grants,
        ];
    }

    private function refundProposal(Proposal $proposal): void
    {
        DB::transaction(function () use ($proposal) {
            $alreadyRefunded = ProposalCreditGrant::query()
                ->where('proposal_id', $proposal->id)
                ->where('source', 'proposal_refund')
                ->exists();

            if ($alreadyRefunded) {
                return;
            }

            $account = $this->lockedAccount($proposal->freelancer);
            $this->prepareAccount($account);
            $grant = ProposalCreditGrant::create([
                'user_id' => $proposal->freelancer_id,
                'proposal_id' => $proposal->id,
                'source' => 'proposal_refund',
                'initial_amount' => $proposal->credit_cost,
                'remaining_amount' => $proposal->credit_cost,
                'granted_at' => now(),
                'expires_at' => now()->addDays(self::FREE_EXPIRY_DAYS),
                'reference' => "Job {$proposal->job_id} cancelled before hire",
            ]);
            $balance = $this->syncBalance($account);

            ProposalCreditTransaction::create([
                'user_id' => $proposal->freelancer_id,
                'proposal_id' => $proposal->id,
                'proposal_credit_grant_id' => $grant->id,
                'type' => 'proposal_refund',
                'amount' => $proposal->credit_cost,
                'balance_after' => $balance,
                'description' => 'Proposal credits returned because the client cancelled the job before hiring.',
            ]);
        });
    }

    private function grantCredits(User $user, int $amount, string $source, ?\DateTimeInterface $expiresAt, string $reference): ProposalCreditGrant
    {
        return DB::transaction(function () use ($user, $amount, $source, $expiresAt, $reference) {
            $account = $this->lockedAccount($user);
            $this->prepareAccount($account);
            $grant = ProposalCreditGrant::create([
                'user_id' => $user->id,
                'source' => $source,
                'initial_amount' => $amount,
                'remaining_amount' => $amount,
                'granted_at' => now(),
                'expires_at' => $expiresAt,
                'reference' => $reference,
            ]);
            $balance = $this->syncBalance($account);
            ProposalCreditTransaction::create([
                'user_id' => $user->id,
                'proposal_credit_grant_id' => $grant->id,
                'type' => "{$source}_grant",
                'amount' => $amount,
                'balance_after' => $balance,
                'description' => $reference,
            ]);

            return $grant;
        });
    }

    private function lockedAccount(User $user): ProposalCreditAccount
    {
        ProposalCreditAccount::firstOrCreate(['user_id' => $user->id]);

        return ProposalCreditAccount::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }

    private function prepareAccount(ProposalCreditAccount $account): void
    {
        $this->expireDueGrants($account);
        $this->grantForCurrentMonth($account);
        $this->syncBalance($account);
    }

    private function expireDueGrants(ProposalCreditAccount $account): void
    {
        $expired = ProposalCreditGrant::query()
            ->where('user_id', $account->user_id)
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->lockForUpdate()
            ->get();

        foreach ($expired as $grant) {
            $amount = $grant->remaining_amount;
            $grant->update(['remaining_amount' => 0]);
            $balance = $this->syncBalance($account);
            ProposalCreditTransaction::create([
                'user_id' => $account->user_id,
                'proposal_credit_grant_id' => $grant->id,
                'type' => 'credit_expired',
                'amount' => -$amount,
                'balance_after' => $balance,
                'description' => "Unused {$this->sourceLabel($grant->source)} credits expired.",
            ]);
        }
    }

    private function grantForCurrentMonth(ProposalCreditAccount $account): void
    {
        $monthStart = now()->startOfMonth();
        if ($account->last_monthly_grant_at?->greaterThanOrEqualTo($monthStart)) {
            return;
        }

        $plan = $this->planFor($account);
        $planGrantBalance = ProposalCreditGrant::query()
            ->where('user_id', $account->user_id)
            ->where('remaining_amount', '>', 0)
            ->whereIn('source', [$plan['monthly_source'], 'legacy_transition'])
            ->sum('remaining_amount');
        $amount = min($plan['monthly_allowance'], max(0, $plan['balance_cap'] - $planGrantBalance));

        if ($amount === 0) {
            return;
        }

        $account->update(['last_monthly_grant_at' => $monthStart]);

        $grant = ProposalCreditGrant::create([
            'user_id' => $account->user_id,
            'source' => $plan['monthly_source'],
            'initial_amount' => $amount,
            'remaining_amount' => $amount,
            'granted_at' => now(),
            'expires_at' => now()->addDays($plan['expiry_days']),
            'reference' => "{$plan['label']} monthly credit grant",
        ]);
        $balance = $this->syncBalance($account);

        ProposalCreditTransaction::create([
            'user_id' => $account->user_id,
            'proposal_credit_grant_id' => $grant->id,
            'type' => 'monthly_grant',
            'amount' => $amount,
            'balance_after' => $balance,
            'description' => "{$plan['label']} monthly Proposal Credit grant",
        ]);
    }

    private function planFor(ProposalCreditAccount $account): array
    {
        $hasPremium = $account->membership_tier === 'premium'
            && (! $account->membership_expires_at || $account->membership_expires_at->isFuture());

        return $hasPremium
            ? [
                'tier' => 'premium',
                'label' => 'Premium',
                'monthly_allowance' => self::PREMIUM_MONTHLY_ALLOWANCE,
                'balance_cap' => self::PREMIUM_BALANCE_CAP,
                'expiry_days' => self::PREMIUM_EXPIRY_DAYS,
                'monthly_source' => 'premium_monthly',
            ]
            : [
                'tier' => 'free',
                'label' => 'Free',
                'monthly_allowance' => self::FREE_MONTHLY_ALLOWANCE,
                'balance_cap' => self::FREE_BALANCE_CAP,
                'expiry_days' => self::FREE_EXPIRY_DAYS,
                'monthly_source' => 'free_monthly',
            ];
    }

    private function activeGrants(ProposalCreditAccount $account): Collection
    {
        return ProposalCreditGrant::query()
            ->where('user_id', $account->user_id)
            ->where('remaining_amount', '>', 0)
            ->orderByRaw('case when expires_at is null then 1 else 0 end')
            ->orderBy('expires_at')
            ->orderBy('granted_at')
            ->orderBy('id')
            ->get();
    }

    private function spendableGrants(ProposalCreditAccount $account): Collection
    {
        return $this->activeGrants($account)->map(function (ProposalCreditGrant $grant) {
            return ProposalCreditGrant::query()->whereKey($grant->id)->lockForUpdate()->firstOrFail();
        });
    }

    private function syncBalance(ProposalCreditAccount $account): int
    {
        $balance = (int) ProposalCreditGrant::query()
            ->where('user_id', $account->user_id)
            ->sum('remaining_amount');
        $account->update(['balance' => $balance]);

        return $balance;
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'free_monthly' => 'Free monthly',
            'premium_monthly' => 'Premium monthly',
            'purchased' => 'Purchased',
            'proposal_refund' => 'Cancellation refund',
            'legacy_transition' => 'Existing balance',
            default => 'Proposal credits',
        };
    }
}
