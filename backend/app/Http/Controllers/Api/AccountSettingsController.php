<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProfileReadinessService;
use App\Services\TrustSummaryService;
use App\Services\MarketplaceReliabilityService;
use Illuminate\Http\Request;

class AccountSettingsController extends Controller
{
    public function show(Request $request, TrustSummaryService $trust, ProfileReadinessService $readiness, MarketplaceReliabilityService $reliability)
    {
        return ['data' => $this->payload($request, $trust, $readiness, $reliability)];
    }

    public function update(Request $request, TrustSummaryService $trust, ProfileReadinessService $readiness, MarketplaceReliabilityService $reliability)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:255']]);
        $request->user()->update($data);

        return ['data' => $this->payload($request, $trust, $readiness, $reliability)];
    }

    private function payload(Request $request, TrustSummaryService $trust, ProfileReadinessService $readiness, MarketplaceReliabilityService $reliability): array
    {
        $user = $request->user()->fresh()->load('roles', 'freelancerProfile', 'clientProfile', 'oauthIdentities', 'portfolioItems', 'freelancerResume');
        $user->clientProfile?->makeVisible([
            'billing_verified',
            'company_verification_note',
            'company_verification_requested_at',
            'company_verified_at',
            'company_verified_by',
        ]);
        $roles = $user->roles->pluck('name')->values();
        $activeRole = $roles->contains($user->active_role) ? $user->active_role : $roles->first();

        if ($user->freelancerProfile) {
            $checklist = $readiness->freelancerChecklist($user);
            $user->freelancerProfile->setAttribute('profile_completeness', $readiness->completion($checklist));
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => filled($user->email_verified_at),
            'password_login_enabled' => $user->oauthIdentities->isEmpty(),
            'account_status' => $user->status,
            'identity_verification_status' => $user->identity_verification_status,
            'identity_verification_note' => $user->identity_verification_note,
            'identity_verification_requested_at' => $user->identity_verification_requested_at,
            'active_role' => $activeRole,
            'roles' => $roles,
            'freelancer_profile' => $user->freelancerProfile,
            'client_profile' => $user->clientProfile,
            'trust_summary' => $trust->for($user),
            'reliability' => $roles->filter(fn (string $role) => in_array($role, ['client', 'freelancer'], true))->mapWithKeys(fn (string $role) => [$role => $reliability->summaryFor($user, $role)])->all(),
        ];
    }
}
