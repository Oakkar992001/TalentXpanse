<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return ['message' => 'Your email address is already verified.'];
        }

        $request->user()->sendEmailVerificationNotification();

        return ['message' => 'Verification email sent.'];
    }

    public function verify(Request $request, int $id, string $hash)
    {
        abort_unless(URL::hasValidSignature($request), 403, 'This verification link is invalid or has expired.');
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'This verification link is invalid.');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->to(rtrim(config('app.frontend_url'), '/').'/settings?verified=1');
    }
}
