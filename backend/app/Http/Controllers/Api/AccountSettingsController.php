<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrustSummaryService;
use Illuminate\Http\Request;

class AccountSettingsController extends Controller
{
    public function show(Request $request, TrustSummaryService $trust)
    {
        return ['data' => $this->payload($request, $trust)];
    }

    public function update(Request $request, TrustSummaryService $trust)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:255']]);
        $request->user()->update($data);

        return ['data' => $this->payload($request, $trust)];
    }

    private function payload(Request $request, TrustSummaryService $trust): array
    {
        $user = $request->user()->fresh()->load('roles', 'freelancerProfile', 'clientProfile', 'oauthIdentities');
        $roles = $user->roles->pluck('name')->values();
        $activeRole = $roles->contains($user->active_role) ? $user->active_role : $roles->first();

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
