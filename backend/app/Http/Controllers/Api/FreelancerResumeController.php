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
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($existing->storage_path)) {
                    Storage::disk($disk)->delete($existing->storage_path);
                }
            }
        }

        return response()->json(['data' => $resume], 201);
    }

    public function downloadProposalResume(Request $request, Proposal $proposal)
    {
        $isOwner = $proposal->freelancer_id === $request->user()->id;
        $isClient = $proposal->job->client_id === $request->user()->id;
        abort_unless($isOwner || $isClient, 403);
        $disk = Storage::disk('local')->exists($proposal->resume_path) ? 'local' : 'public';
        abort_unless($proposal->resume_path && Storage::disk($disk)->exists($proposal->resume_path), 404, 'This CV is no longer available.');

        return Storage::disk($disk)->download($proposal->resume_path, $proposal->resume_name);
    }

    private function ensureFreelancer(Request $request): void
    {
        abort_unless($request->user()->hasRole('freelancer'), 403, 'Add the Freelancer role to upload a CV.');
    }
}
