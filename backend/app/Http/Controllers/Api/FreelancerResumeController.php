<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FreelancerResume;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FreelancerResumeController extends Controller
{
    public function store(Request $request)
    {
        $this->ensureFreelancer($request);
        $request->validate(['resume' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $file = $request->file('resume');
        $existing = FreelancerResume::where('user_id', $request->user()->id)->first();
        $path = $file->store("resumes/{$request->user()->id}", 'local');
        $resume = FreelancerResume::updateOrCreate(['user_id' => $request->user()->id], [
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'file_size' => $file->getSize(),
        ]);

        if ($existing && $existing->storage_path !== $path) {
            $this->deleteFileUnlessAttachedToProposal($request, $existing->storage_path);
        }

        return response()->json(['data' => $resume], 201);
    }

    public function destroy(Request $request)
    {
        $this->ensureFreelancer($request);
        $resume = FreelancerResume::where('user_id', $request->user()->id)->first();

        if (! $resume) {
            return response()->noContent();
        }

        $resume->delete();
        $this->deleteFileUnlessAttachedToProposal($request, $resume->storage_path);

        return response()->noContent();
    }

    public function downloadProposalResume(Request $request, Proposal $proposal)
    {
        $isOwner = $proposal->freelancer_id === $request->user()->id;
        $isClient = $proposal->job->client_id === $request->user()->id;
        abort_unless($isOwner || $isClient, 403);
        abort_unless($proposal->resume_path, 404, 'This proposal does not include a CV.');

        $disk = Storage::disk('local')->exists($proposal->resume_path) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($proposal->resume_path), 404, 'This CV is no longer available.');

        return Storage::disk($disk)->download($proposal->resume_path, $proposal->resume_name);
    }

    private function ensureFreelancer(Request $request): void
    {
        abort_unless($request->user()->hasRole('freelancer'), 403, 'Add the Freelancer role to upload a CV.');
    }

    private function deleteFileUnlessAttachedToProposal(Request $request, string $path): void
    {
        $attachedToProposal = Proposal::query()
            ->where('freelancer_id', $request->user()->id)
            ->where('resume_path', $path)
            ->exists();

        if ($attachedToProposal) {
            return;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }
}
