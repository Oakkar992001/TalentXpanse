<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceTwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SecurityController extends Controller
{
    public function show(Request $request)
    {
        $current = $request->user()->currentAccessToken();

        return ['data' => [
            'two_factor_enabled' => filled($request->user()->two_factor_confirmed_at),
            'two_factor_enforced_for_admins' => (bool) config('marketplace_security.admin_mfa_required'),
            'sessions' => $request->user()->tokens()->latest('last_used_at')->latest('created_at')->get()->map(fn ($token) => [
                'id' => $token->id,
                'label' => $token->name,
                'current' => $current?->id === $token->id,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ])->values(),
        ]];
    }

    public function setup(Request $request, MarketplaceTwoFactorService $twoFactor)
    {
        $user = $request->user();
        if ($user->two_factor_confirmed_at) {
            abort(422, 'Two-factor authentication is already enabled.');
        }
        $secret = $twoFactor->newSecret();
        $user->update(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => null]);

        return ['data' => ['secret' => $secret, 'otpauth_uri' => $twoFactor->qrUri($user, $secret)]];
    }

    public function confirm(Request $request, MarketplaceTwoFactorService $twoFactor)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $user = $request->user();
        if (! $user->two_factor_secret || ! $twoFactor->verify($user, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Enter the current six-digit code from your authenticator app.']);
        }
        $codes = $twoFactor->recoveryCodes();
        $user->update([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn ($code) => password_hash($code, PASSWORD_DEFAULT), $codes),
        ]);

        return ['data' => ['recovery_codes' => $codes], 'message' => 'Two-factor authentication is now enabled. Store these recovery codes somewhere safe.'];
    }

    public function disable(Request $request, MarketplaceTwoFactorService $twoFactor)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $user = $request->user();
        if (! $user->two_factor_confirmed_at || ! $twoFactor->verify($user, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Enter a valid authenticator or recovery code to disable two-factor authentication.']);
        }
        $user->update(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null]);

        return response()->json(['message' => 'Two-factor authentication was disabled.']);
    }

    public function revokeOtherSessions(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;
        $request->user()->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->delete();

        return response()->json(['message' => 'Other signed-in devices were signed out.']);
    }

    public function revokeSession(Request $request, int $tokenId)
    {
        $token = $request->user()->tokens()->findOrFail($tokenId);
        abort_if($request->user()->currentAccessToken()?->id === $token->id, 422, 'Use Logout to end this current session.');
        $token->delete();

        return response()->noContent();
    }
}
