<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IdentityVerificationSubmission;
use App\Support\MarketplaceStorage;
use App\Services\MarketplaceUploadSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class VerificationRequestController extends Controller
{
    public function request(Request $request, MarketplaceUploadSafetyService $safety)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['identity', 'company'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'nrc_front' => [Rule::requiredIf($request->input('type') === 'identity'), 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'nrc_back' => [Rule::requiredIf($request->input('type') === 'identity'), 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $user = $request->user();

        if ($data['type'] === 'identity') {
            abort_if($user->identity_verification_status === 'verified', 422, 'Your identity is already verified.');
            abort_if(IdentityVerificationSubmission::query()->where('user_id', $user->id)->where('status', 'pending')->exists(), 422, 'Your identity verification request is already being reviewed.');
            $safety->inspect($request->file('nrc_front'), 'identity_document');
            $safety->inspect($request->file('nrc_back'), 'identity_document');

            $paths = [];

            try {
                $paths['front'] = $request->file('nrc_front')->store("identity-documents/{$user->id}", MarketplaceStorage::privateDisk());
                $paths['back'] = $request->file('nrc_back')->store("identity-documents/{$user->id}", MarketplaceStorage::privateDisk());

                $submission = DB::transaction(function () use ($user, $data, $paths) {
                    $submission = IdentityVerificationSubmission::create([
                        'user_id' => $user->id,
                        'nrc_front_path' => $paths['front'],
                        'nrc_back_path' => $paths['back'],
                        'submitted_note' => $data['note'] ?? null,
                        'status' => 'pending',
                        'submitted_at' => now(),
                    ]);

                    $user->update([
                        'identity_verification_status' => 'pending',
                        'identity_verification_note' => null,
                        'identity_verification_requested_at' => now(),
                        'identity_verified_at' => null,
                        'identity_verified_by' => null,
                    ]);

                    return $submission;
                });
            } catch (Throwable $exception) {
                foreach ($paths as $path) {
                    Storage::disk(MarketplaceStorage::privateDisk())->delete($path);
                }

                throw $exception;
            }

            return response()->json(['data' => ['id' => $submission->id, 'type' => 'identity', 'status' => 'pending', 'submitted_at' => $submission->submitted_at]], 201);
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
