<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProfileReadinessService;
use App\Services\TrustSummaryService;
use Illuminate\Http\Request;

class AccountSettingsController extends Controller
{
    public function show(Request $request, TrustSummaryService $trust, ProfileReadinessService $readiness)
    {
        return ['data' => $this->payload($request, $trust, $readiness)];
    }

    public function update(Request $request, TrustSummaryService $trust, ProfileReadinessService $readiness)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:255']]);
        $request->user()->update($data);

        return ['data' => $this->payload($request, $trust, $readiness)];
    }

    private function payload(Request $request, TrustSummaryService $trust, ProfileReadinessService $readiness): array
    {
        $user = $request->user()->fresh()->load('roles', 'freelancerProfile', 'clientProfile', 'oauthIdentities', 'portfolioItems', 'freelancerResume');
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
            'active_role' => $activeRole,
            'roles' => $roles,
            'freelancer_profile' => $user->freelancerProfile,
            'client_profile' => $user->clientProfile,
            'trust_summary' => $trust->for($user),
        ];
    }
}
