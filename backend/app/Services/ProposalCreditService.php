<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Proposal;
use App\Models\ProposalCreditAccount;
use App\Models\ProposalCreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposalCreditService
{
    public const MONTHLY_ALLOWANCE = 20;

    public const BALANCE_CAP = 40;

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
            $this->grantForCurrentMonth($account);

            return $account->fresh();
        });
    }

    public function deductForProposal(User $user, Job $job, ?Proposal $proposal = null): int
    {
        return DB::transaction(function () use ($user, $job, $proposal) {
            $account = $this->lockedAccount($user);
            $this->grantForCurrentMonth($account);
            $cost = $this->costFor($job);

            if ($account->balance < $cost) {
                throw ValidationException::withMessages([
                    'credits' => "This proposal costs {$cost} credits, but you have {$account->balance}. Your next monthly grant is available next month.",
                ]);
            }

            $account->update(['balance' => $account->balance - $cost]);

            ProposalCreditTransaction::create([
                'user_id' => $user->id,
                'proposal_id' => $proposal?->id,
                'type' => 'proposal_submission',
                'amount' => -$cost,
                'balance_after' => $account->balance,
                'description' => "Proposal for: {$job->title}",
            ]);

            return $cost;
        });
    }

    public function summaryFor(User $user): array
    {
        $account = $this->accountFor($user);

        return [
            'balance' => $account->balance,
            'monthly_allowance' => self::MONTHLY_ALLOWANCE,
            'balance_cap' => self::BALANCE_CAP,
            'next_grant_on' => now()->startOfMonth()->addMonth()->toDateString(),
        ];
    }

    private function lockedAccount(User $user): ProposalCreditAccount
    {
        ProposalCreditAccount::firstOrCreate(['user_id' => $user->id]);

        return ProposalCreditAccount::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }

    private function grantForCurrentMonth(ProposalCreditAccount $account): void
    {
        $monthStart = now()->startOfMonth();

        if ($account->last_monthly_grant_at?->greaterThanOrEqualTo($monthStart)) {
            return;
        }

        $amount = min(self::MONTHLY_ALLOWANCE, self::BALANCE_CAP - $account->balance);
        $account->update(['balance' => $account->balance + $amount, 'last_monthly_grant_at' => $monthStart]);

        if ($amount > 0) {
            ProposalCreditTransaction::create([
                'user_id' => $account->user_id,
                'type' => 'monthly_grant',
                'amount' => $amount,
                'balance_after' => $account->balance,
                'description' => 'Monthly Proposal Credit grant',
            ]);
        }
    }
}
