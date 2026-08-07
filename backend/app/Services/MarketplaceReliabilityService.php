<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractReview;
use App\Models\MarketplaceReliabilityEvent;
use App\Models\MarketplaceReliabilityProfile;
use App\Models\User;
use Illuminate\Support\Collection;

class MarketplaceReliabilityService
{
    public function summaryFor(User $user, string $role, bool $includeHistory = true): array
    {
        $profile = $this->sync($user, $role);
        $summary = [
            'role' => $role,
            'score' => $profile->score,
            'tier' => $profile->tier,
            'tier_label' => $this->tierLabel($profile->tier),
            'search_visibility' => $profile->search_visibility,
            'visibility_label' => $this->visibilityLabel($profile->search_visibility),
            'completed_projects_count' => $profile->completed_projects_count,
            'positive_reviews_count' => $profile->positive_reviews_count,
            'average_rating' => $profile->average_rating,
            'active_penalty_points' => $profile->active_penalty_points,
            'next_step' => $this->nextStep($profile),
        ];

        if ($includeHistory) {
            $summary['recent_events'] = MarketplaceReliabilityEvent::query()
                ->where('user_id', $user->id)
                ->where('role', $role)
                ->whereIn('status', ['pending', 'confirmed'])
                ->latest()
                ->take(8)
                ->get()
                ->map(fn (MarketplaceReliabilityEvent $event) => $this->eventPayload($event))
                ->values();
        }

        return $summary;
    }

    public function publicSummaryFor(User $user, string $role): array
    {
        $summary = $this->summaryFor($user, $role, false);

        return collect($summary)->only([
            'tier',
            'tier_label',
            'completed_projects_count',
            'positive_reviews_count',
            'average_rating',
        ])->all();
    }

    public function recordCompletedContract(Contract $contract): void
    {
        $contract->loadMissing(['client', 'freelancer']);
        $this->recordSystemEvent($contract->client, 'client', 'project_completed', 4, 'contract', $contract->id, 'Completed a project through TalentXpanse.');
        $this->recordSystemEvent($contract->freelancer, 'freelancer', 'project_completed', 4, 'contract', $contract->id, 'Completed a project through TalentXpanse.');
    }

    public function recordPositiveReview(ContractReview $review, Contract $contract): void
    {
        if ($review->rating < 4) {
            return;
        }

        $role = $contract->client_id === $review->reviewed_user_id ? 'client' : 'freelancer';
        $this->recordSystemEvent($review->reviewedUser, $role, 'positive_review', 2, 'review', $review->id, 'Received a positive project review.');
    }

    public function recordVerification(User $user, string $role, string $type, int $sourceId): void
    {
        $eventType = $type === 'company' ? 'company_verified' : 'identity_verified';
        $sourceType = $type === 'company' ? 'client_profile' : 'user';
        $this->recordSystemEvent($user, $role, $eventType, 4, $sourceType, $sourceId, 'Completed marketplace verification.');
    }

    public function recordCancellationConcern(User $user, string $role, Contract $contract, string $reasonCode, string $details): MarketplaceReliabilityEvent
    {
        return MarketplaceReliabilityEvent::firstOrCreate([
            'user_id' => $user->id,
            'role' => $role,
            'event_type' => 'cancellation_concern',
            'source_type' => 'contract',
            'source_id' => $contract->id,
        ], [
            'points' => -8,
            'status' => 'pending',
            'reason_code' => $reasonCode,
            'details' => $details,
            'metadata' => ['contract_title' => $contract->title],
        ]);
    }

    public function recordReportAction(User $user, string $role, string $action, int $reportId, ?string $details = null, ?User $administrator = null): MarketplaceReliabilityEvent
    {
        $points = $action === 'serious_violation' ? -20 : -5;
        $event = MarketplaceReliabilityEvent::firstOrNew([
            'user_id' => $user->id,
            'role' => $role,
            'event_type' => 'moderation_action',
            'source_type' => 'report',
            'source_id' => $reportId,
        ]);
        $event->fill([
            'points' => $points,
            'status' => 'confirmed',
            'reason_code' => $action,
            'details' => $details,
            'metadata' => ['action' => $action],
            'reviewed_by' => $administrator?->id,
            'reviewed_at' => $administrator ? now() : null,
            'effective_at' => now(),
            'expires_at' => now()->addDays($action === 'serious_violation' ? 180 : 60),
        ]);
        $event->save();

        return $event;
    }

    public function resolve(MarketplaceReliabilityEvent $event, string $status, User $administrator, string $note): MarketplaceReliabilityEvent
    {
        $event->update([
            'status' => $status,
            'metadata' => [...($event->metadata ?? []), 'resolution_note' => $note],
            'reviewed_by' => $administrator->id,
            'reviewed_at' => now(),
            'effective_at' => $status === 'confirmed' ? now() : null,
            'expires_at' => $status === 'confirmed' ? now()->addDays(90) : null,
        ]);
        $this->sync($event->user, $event->role);

        return $event->fresh(['user', 'reviewer']);
    }

    public function sync(User $user, string $role): MarketplaceReliabilityProfile
    {
        $this->ensureRole($role);
        $this->backfillSystemEvents($user, $role);

        $profile = MarketplaceReliabilityProfile::firstOrCreate(['user_id' => $user->id, 'role' => $role]);
        $events = $this->activeEvents($user, $role);
        $completedProjects = $this->completedContracts($user, $role)->count();
        $reviews = $this->reviewsForRole($user, $role);
        $positiveReviews = (clone $reviews)->where('rating', '>=', 4)->count();
        $averageRating = (clone $reviews)->avg('rating');
        $eventPoints = (int) $events->sum('points');
        $penalties = (int) $events->where('points', '<', 0)->sum('points');
        $score = max(0, min(100, 50 + $eventPoints));

        $profile->update([
            'score' => $score,
            'tier' => $this->tierFor($completedProjects, $averageRating, $score, $this->isVerified($user, $role)),
            'search_visibility' => $this->visibilityFor($score, $penalties),
            'completed_projects_count' => $completedProjects,
            'positive_reviews_count' => $positiveReviews,
            'average_rating' => $averageRating,
            'active_penalty_points' => $penalties,
            'last_synced_at' => now(),
        ]);

        return $profile->fresh();
    }

    private function backfillSystemEvents(User $user, string $role): void
    {
        $this->completedContracts($user, $role)->each(fn (Contract $contract) => $this->recordSystemEvent($user, $role, 'project_completed', 4, 'contract', $contract->id, 'Completed a project through TalentXpanse.'));

        $this->reviewsForRole($user, $role)
            ->where('rating', '>=', 4)
            ->get()
            ->each(fn (ContractReview $review) => $this->recordSystemEvent($user, $role, 'positive_review', 2, 'review', $review->id, 'Received a positive project review.'));

        if ($user->identity_verification_status === 'verified') {
            $this->recordVerification($user, $role, 'identity', $user->id);
        }
        if ($role === 'client' && $user->clientProfile?->company_verification_status === 'verified') {
            $this->recordVerification($user, $role, 'company', $user->clientProfile->id);
        }
    }

    private function recordSystemEvent(?User $user, string $role, string $eventType, int $points, string $sourceType, int $sourceId, string $details): void
    {
        if (! $user) {
            return;
        }

        MarketplaceReliabilityEvent::firstOrCreate([
            'user_id' => $user->id,
            'role' => $role,
            'event_type' => $eventType,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ], [
            'points' => $points,
            'status' => 'confirmed',
            'details' => $details,
            'effective_at' => now(),
        ]);
    }

    private function completedContracts(User $user, string $role): Collection
    {
        return Contract::query()
            ->where('status', 'completed')
            ->where($role === 'client' ? 'client_id' : 'freelancer_id', $user->id)
            ->get(['id', 'client_id', 'freelancer_id', 'title']);
    }

    private function reviewsForRole(User $user, string $role)
    {
        return ContractReview::query()
            ->where('reviewed_user_id', $user->id)
            ->whereHas('contract', fn ($query) => $query
                ->where('status', 'completed')
                ->where($role === 'client' ? 'client_id' : 'freelancer_id', $user->id));
    }

    private function activeEvents(User $user, string $role): Collection
    {
        return MarketplaceReliabilityEvent::query()
            ->where('user_id', $user->id)
            ->where('role', $role)
            ->where('status', 'confirmed')
            ->where(fn ($query) => $query->whereNull('effective_at')->orWhere('effective_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();
    }

    private function isVerified(User $user, string $role): bool
    {
        return $user->identity_verification_status === 'verified'
            || ($role === 'client' && $user->clientProfile?->company_verification_status === 'verified');
    }

    private function tierFor(int $completedProjects, ?float $averageRating, int $score, bool $verified): string
    {
        if ($completedProjects >= 10 && $verified && $score >= 75 && ($averageRating === null || $averageRating >= 4.5)) {
            return 'proven';
        }
        if ($completedProjects >= 3 && $score >= 60 && ($averageRating === null || $averageRating >= 4.0)) {
            return 'reliable';
        }
        if ($completedProjects >= 1 || $verified) {
            return 'building';
        }

        return 'new';
    }

    private function visibilityFor(int $score, int $penalties): string
    {
        if ($score < 30 || $penalties <= -20) {
            return 'limited';
        }
        if ($score < 45 || $penalties <= -5) {
            return 'reduced';
        }

        return 'standard';
    }

    private function nextStep(MarketplaceReliabilityProfile $profile): string
    {
        if ($profile->search_visibility !== 'standard') {
            return 'Complete successful projects and maintain professional communication to rebuild normal marketplace reach.';
        }
        if ($profile->tier === 'new') {
            return 'Complete your profile and your first project to begin building reliability.';
        }
        if ($profile->tier === 'building') {
            return 'Complete more projects and collect fair feedback to reach Reliable status.';
        }
        if ($profile->tier === 'reliable') {
            return 'Keep completing quality work and verify your account to work toward Proven status.';
        }

        return 'Keep your strong project record and professional communication to maintain Proven status.';
    }

    private function tierLabel(string $tier): string
    {
        return match ($tier) {
            'building' => 'Building',
            'reliable' => 'Reliable',
            'proven' => 'Proven',
            default => 'New',
        };
    }

    private function visibilityLabel(string $visibility): string
    {
        return match ($visibility) {
            'reduced' => 'Reduced reach',
            'limited' => 'Limited reach',
            default => 'Normal reach',
        };
    }

    private function eventPayload(MarketplaceReliabilityEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'points' => $event->points,
            'status' => $event->status,
            'reason_code' => $event->reason_code,
            'details' => $event->details,
            'resolution_note' => $event->metadata['resolution_note'] ?? null,
            'created_at' => $event->created_at,
            'expires_at' => $event->expires_at,
        ];
    }

    private function ensureRole(string $role): void
    {
        abort_unless(in_array($role, ['client', 'freelancer'], true), 422, 'Reliability is only available for client and freelancer workspaces.');
    }
}
