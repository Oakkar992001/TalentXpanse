<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();
        abort_if($user->oauthIdentities()->exists(), 422, 'This account uses Google sign-in. Password setup is not available yet.');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Your current password is incorrect.']]);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Choose a new password that is different from your current password.']]);
        }

        $user->update(['password' => $data['password']]);
        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->whereKeyNot($currentTokenId)->delete();
        }

        return ['message' => 'Password updated. Other active sessions have been signed out.'];
    }
}
