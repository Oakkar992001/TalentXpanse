<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VerificationRequestController extends Controller
{
    public function request(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['identity', 'company'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $request->user();

        if ($data['type'] === 'identity') {
            abort_if($user->identity_verification_status === 'verified', 422, 'Your identity is already verified.');
            abort_if($user->identity_verification_status === 'pending', 422, 'Your identity verification request is already being reviewed.');
            $user->update([
                'identity_verification_status' => 'pending',
                'identity_verification_note' => $data['note'] ?? null,
                'identity_verification_requested_at' => now(),
                'identity_verified_at' => null,
                'identity_verified_by' => null,
            ]);

            return ['data' => ['type' => 'identity', 'status' => 'pending']];
        }

        abort_unless($user->hasRole('client'), 403, 'Add the Client role before requesting company verification.');
        $profile = $user->clientProfile;
        abort_unless($profile && filled($profile->company_name), 422, 'Add your company details before requesting company verification.');
        abort_if($profile->company_verification_status === 'verified', 422, 'Your company is already verified.');
        abort_if($profile->company_verification_status === 'pending', 422, 'Your company verification request is already being reviewed.');
        $profile->update([
            'company_verification_status' => 'pending',
            'company_verification_note' => $data['note'] ?? null,
            'company_verification_requested_at' => now(),
            'company_verified_at' => null,
            'company_verified_by' => null,
        ]);

        return ['data' => ['type' => 'company', 'status' => 'pending']];
    }
}
