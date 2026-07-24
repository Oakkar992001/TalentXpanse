<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProposalController extends Controller
{
    public function store(Request $request, Job $job)
    {
        abort_unless($request->user()->hasRole('freelancer'), 403, 'Add the Freelancer role before submitting a proposal.');
        abort_unless($job->status === 'open', 422, 'This job is no longer accepting proposals.');

        $data = $request->validate([
            'cover_letter' => ['required', 'string', 'min:40', 'max:4000'],
            'bid_amount' => ['required', 'integer', 'min:1000'],
            'delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $proposal = Proposal::firstOrCreate(
            ['job_id' => $job->id, 'freelancer_id' => $request->user()->id],
            $data
        );

        if (! $proposal->wasRecentlyCreated) {
            return response()->json(['message' => 'You have already submitted a proposal for this job.'], 422);
        }

        return response()->json(['data' => $proposal->load('job')], 201);
    }

    public function mine(Request $request)
    {
        abort_unless($request->user()->hasRole('freelancer'), 403);

        return ['data' => $request->user()->proposals()->with('job.client.clientProfile')->latest()->get()];
    }

    public function forJob(Request $request, Job $job)
    {
        abort_unless($job->client_id === $request->user()->id, 403, 'Only the job owner can view proposals.');

        return ['data' => $job->proposals()->with('freelancer.freelancerProfile')->latest()->get()];
    }

    public function updateStatus(Request $request, Proposal $proposal)
    {
        abort_unless($proposal->job->client_id === $request->user()->id, 403, 'Only the job owner can manage proposals.');
        $data = $request->validate(['status' => ['required', Rule::in(['shortlisted', 'accepted', 'declined'])]]);
        $proposal->update($data);

        return ['data' => $proposal->fresh('job')];
    }
}
